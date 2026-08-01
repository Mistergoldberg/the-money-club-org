#!/usr/bin/env python3
"""Map an exact transcript onto Faster Whisper word timestamps."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import unicodedata
from difflib import SequenceMatcher
from pathlib import Path

from faster_whisper import WhisperModel


TIMING_RE = re.compile(
    r"(?P<start>\d{2}:\d{2}:\d{2},\d{3})\s+-->\s+"
    r"(?P<end>\d{2}:\d{2}:\d{2},\d{3})"
)
MIN_CUE_DURATION = 0.04


def normalize_word(value: str) -> str:
    value = unicodedata.normalize("NFKD", value).lower().replace("’", "'")
    return "".join(character for character in value if character.isalnum())


def read_srt_words(path: Path) -> list[str]:
    blocks = re.split(r"\n\s*\n", path.read_text(encoding="utf-8-sig").strip())
    words = []
    for block in blocks:
        lines = block.splitlines()
        if len(lines) >= 3 and TIMING_RE.fullmatch(lines[1].strip()):
            words.append(" ".join(lines[2:]).strip())
    if not words:
        raise RuntimeError(f"No valid cues found in {path}")
    return words


def read_transcript_words(path: Path) -> list[str]:
    words = re.findall(r"\S+", path.read_text(encoding="utf-8-sig"))
    if not words:
        raise RuntimeError(f"No words found in {path}")
    return words


def transcribe_words(audio: Path, model_name: str) -> tuple[list[dict], dict]:
    model = WhisperModel(model_name, device="cpu", compute_type="int8")
    segments, info = model.transcribe(
        str(audio),
        language="en",
        beam_size=5,
        best_of=5,
        temperature=0,
        word_timestamps=True,
        vad_filter=False,
        condition_on_previous_text=True,
    )

    words = []
    segment_count = 0
    for segment in segments:
        segment_count += 1
        for item in segment.words or []:
            normalized = normalize_word(item.word)
            if not normalized or item.start is None or item.end is None:
                continue
            words.append(
                {
                    "word": item.word.strip(),
                    "normalized": normalized,
                    "start": float(item.start),
                    "end": float(item.end),
                    "probability": float(item.probability),
                }
            )
    return words, {
        "language": info.language,
        "language_probability": info.language_probability,
        "duration": info.duration,
        "segments": segment_count,
    }


def build_matches(expected: list[str], recognized: list[dict]) -> dict[int, int]:
    expected_normalized = [normalize_word(word) for word in expected]
    recognized_normalized = [word["normalized"] for word in recognized]
    matcher = SequenceMatcher(
        None, expected_normalized, recognized_normalized, autojunk=False
    )
    matches = {}
    for block in matcher.get_matching_blocks():
        for offset in range(block.size):
            matches[block.a + offset] = block.b + offset
    return matches


def interpolate_words(
    expected: list[str], recognized: list[dict], matches: dict[int, int], duration: float
) -> list[dict]:
    aligned: list[dict | None] = [None] * len(expected)
    for expected_index, recognized_index in matches.items():
        anchor = recognized[recognized_index]
        aligned[expected_index] = {
            "word": expected[expected_index],
            "start": anchor["start"],
            "end": anchor["end"],
            "matched": True,
        }

    matched_indices = sorted(matches)
    if not matched_indices:
        raise RuntimeError("No transcript words matched the recognized speech.")

    runs = []
    run_start = None
    for index, item in enumerate(aligned):
        if item is None and run_start is None:
            run_start = index
        if item is not None and run_start is not None:
            runs.append((run_start, index - 1))
            run_start = None
    if run_start is not None:
        runs.append((run_start, len(aligned) - 1))

    for start_index, end_index in runs:
        count = end_index - start_index + 1
        previous_index = start_index - 1
        next_index = end_index + 1

        window_start = (
            aligned[previous_index]["end"] if previous_index >= 0 else 0.0
        )
        window_end = (
            aligned[next_index]["start"]
            if next_index < len(aligned)
            else duration
        )

        if window_end <= window_start:
            window_end = min(duration, window_start + max(0.12 * count, 0.12))

        step = (window_end - window_start) / count
        for offset, index in enumerate(range(start_index, end_index + 1)):
            word_start = window_start + step * offset
            word_end = window_start + step * (offset + 1)
            aligned[index] = {
                "word": expected[index],
                "start": word_start,
                "end": word_end,
                "matched": False,
            }

    result = [item for item in aligned if item is not None]
    for index, item in enumerate(result):
        item["start"] = max(0.0, min(float(item["start"]), duration))
        item["end"] = max(item["start"] + 0.04, min(float(item["end"]), duration))
        if index + 1 < len(result) and item["end"] > result[index + 1]["start"]:
            next_start = float(result[index + 1]["start"])
            item["end"] = next_start
            if item["end"] <= item["start"]:
                previous_end = float(result[index - 1]["end"]) if index else 0.0
                item["start"] = max(previous_end, next_start - 0.04)
    return result


def repair_cue_boundaries(
    words: list[dict], duration: float, minimum_duration: float = MIN_CUE_DURATION
) -> list[dict]:
    """Keep one-word cues readable when Whisper collapses adjacent boundaries."""
    if not words:
        return words

    latest_start = max(0.0, duration - minimum_duration)
    for index, item in enumerate(words):
        start = max(0.0, min(float(item["start"]), latest_start))
        if index:
            start = max(start, words[index - 1]["start"] + minimum_duration)
        item["start"] = min(start, latest_start)

    for index, item in enumerate(words):
        start = float(item["start"])
        next_start = (
            float(words[index + 1]["start"]) if index + 1 < len(words) else duration
        )
        end = max(float(item["end"]), start + minimum_duration)
        if next_start > start:
            end = min(end, next_start)
        item["end"] = max(start + minimum_duration, min(end, duration))

    return words


def format_srt_time(seconds: float) -> str:
    milliseconds = max(0, round(seconds * 1000))
    hours, remainder = divmod(milliseconds, 3_600_000)
    minutes, remainder = divmod(remainder, 60_000)
    secs, millis = divmod(remainder, 1000)
    return f"{hours:02d}:{minutes:02d}:{secs:02d},{millis:03d}"


def format_ass_time(seconds: float) -> str:
    centiseconds = max(0, round(seconds * 100))
    hours, remainder = divmod(centiseconds, 360_000)
    minutes, remainder = divmod(remainder, 6_000)
    secs, centis = divmod(remainder, 100)
    return f"{hours:d}:{minutes:02d}:{secs:02d}.{centis:02d}"


def write_srt(path: Path, words: list[dict]) -> None:
    blocks = []
    for index, item in enumerate(words, 1):
        blocks.append(
            f"{index}\n"
            f"{format_srt_time(item['start'])} --> {format_srt_time(item['end'])}\n"
            f"{item['word']}"
        )
    path.write_text("\n\n".join(blocks) + "\n", encoding="utf-8")


def write_ass(path: Path, words: list[dict]) -> None:
    header = """[Script Info]
Title: Financial Literacy Coffee Counter - Word-Aligned
ScriptType: v4.00+
PlayResX: 1920
PlayResY: 1080
WrapStyle: 2
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: CenterWord,Raleway,84,&H00FFFFFF,&H00FFFFFF,&H00000000,&H00000000,0,0,0,0,100,100,0,0,1,0,0,5,140,140,80,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""
    events = []
    for item in words:
        text = (
            item["word"]
            .replace("\\", r"\\")
            .replace("{", r"\{")
            .replace("}", r"\}")
        )
        events.append(
            "Dialogue: 0,"
            f"{format_ass_time(item['start'])},"
            f"{format_ass_time(item['end'])},"
            f"CenterWord,,0,0,0,,{text}"
        )
    path.write_text(header + "\n".join(events) + "\n", encoding="utf-8")


def write_words_json(path: Path, words: list[dict]) -> None:
    path.write_text(json.dumps(words, indent=2) + "\n", encoding="utf-8")


def validate_alignment(expected: list[str], aligned: list[dict], duration: float) -> None:
    if [item["word"] for item in aligned] != expected:
        raise RuntimeError("Aligned output does not contain the exact transcript words.")

    previous_start = -1.0
    for index, item in enumerate(aligned):
        if item["start"] < previous_start:
            raise RuntimeError(f"Non-monotonic start time at word {index + 1}.")
        if item["end"] <= item["start"]:
            raise RuntimeError(f"Invalid duration at word {index + 1}.")
        if item["end"] > duration + 0.001:
            raise RuntimeError(f"Word {index + 1} extends beyond the audio.")
        previous_start = item["start"]


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--audio", type=Path, required=True)
    source = parser.add_mutually_exclusive_group(required=True)
    source.add_argument("--transcript", type=Path)
    source.add_argument("--source-srt", type=Path)
    parser.add_argument("--output-srt", type=Path, required=True)
    parser.add_argument("--output-ass", type=Path, required=True)
    parser.add_argument("--output-json", type=Path)
    parser.add_argument("--report", type=Path, required=True)
    parser.add_argument("--model", default="small.en")
    args = parser.parse_args()

    source_path = args.transcript or args.source_srt
    expected = (
        read_transcript_words(args.transcript)
        if args.transcript
        else read_srt_words(args.source_srt)
    )
    recognized, info = transcribe_words(args.audio, args.model)
    matches = build_matches(expected, recognized)
    aligned = interpolate_words(expected, recognized, matches, info["duration"])
    aligned = repair_cue_boundaries(aligned, info["duration"])
    validate_alignment(expected, aligned, info["duration"])

    write_srt(args.output_srt, aligned)
    write_ass(args.output_ass, aligned)
    if args.output_json:
        write_words_json(args.output_json, aligned)

    report = {
        **info,
        "model": args.model,
        "audio": args.audio.name,
        "audio_sha256": hashlib.sha256(args.audio.read_bytes()).hexdigest(),
        "source": source_path.name,
        "source_sha256": hashlib.sha256(source_path.read_bytes()).hexdigest(),
        "expected_words": len(expected),
        "output_words": len(aligned),
        "recognized_words": len(recognized),
        "direct_matches": len(matches),
        "interpolated_words": len(expected) - len(matches),
        "match_rate": len(matches) / len(expected),
        "exact_transcript_preserved": [item["word"] for item in aligned] == expected,
        "interpolated_details": [
            item for item in aligned if not item["matched"]
        ],
        "first_word": aligned[0],
        "last_word": aligned[-1],
    }
    args.report.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
