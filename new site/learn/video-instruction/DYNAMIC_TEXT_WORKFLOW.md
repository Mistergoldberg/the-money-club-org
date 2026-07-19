# Dynamic Text Video Workflow

## Inputs

Each video starts from two final, immutable source files:

1. The exact transcript in UTF-8 plain text.
2. The final MP3 voice-over.

Do not edit or regenerate the voice-over after alignment. A changed MP3 requires a
new alignment run.

## Align

Run:

```sh
./align_dynamic_text.sh \
  "voice-over.mp3" \
  "transcript.txt" \
  "video-slug"
```

The command creates:

- `video-slug_word_aligned.srt`: one exact transcript token per cue.
- `video-slug_word_aligned.ass`: the same timing with the standard visual style.
- `video-slug_word_timings.json`: renderer-friendly word timing data.
- `video-slug_alignment_report.json`: QA totals, hashes, match rate, and interpolated words.

The transcript controls the displayed text. Whisper supplies timing anchors only.
Words that speech recognition splits differently, such as hyphenated compounds,
are interpolated between adjacent anchors and identified in the report.

## QA Gate

Do not render unless the report confirms:

- `expected_words` equals `output_words`.
- `exact_transcript_preserved` is `true`.
- `interpolated_details` has been reviewed.
- The audio and transcript hashes match the approved source files.
- No cue has a zero or negative duration.
- Cue start times are monotonic.

## Preview

Update the `<audio>` source and SRT filename in `index.html`, then serve the
directory over HTTP:

```sh
python3 -m http.server 5500
```

Open:

```text
http://127.0.0.1:5500/
```

The player uses `requestAnimationFrame` while audio is playing. Do not replace
this with audio `timeupdate` events alone; short words can be skipped because
those events fire too slowly.

## Production Recommendation

Use `*_word_timings.json` as the canonical timing artifact for final video
rendering. A reusable Remotion composition can consume this JSON, render at a
fixed frame rate, and export horizontal and vertical variants from the same
inputs. SRT remains useful for editorial review and external caption tracks, but
it should not be the production database.
