#!/bin/sh
set -eu

if [ "$#" -lt 3 ] || [ "$#" -gt 4 ]; then
  echo "Usage: $0 AUDIO TRANSCRIPT OUTPUT_SLUG [MODEL]" >&2
  exit 2
fi

AUDIO=$1
TRANSCRIPT=$2
SLUG=$3
MODEL=${4:-small.en}
SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
VENV="$SCRIPT_DIR/.venv"

if [ ! -x "$VENV/bin/python" ]; then
  /opt/homebrew/bin/python3.12 -m venv "$VENV"
  "$VENV/bin/pip" install -r "$SCRIPT_DIR/alignment-requirements.txt"
fi

"$VENV/bin/python" "$SCRIPT_DIR/realign_from_whisper.py" \
  --audio "$AUDIO" \
  --transcript "$TRANSCRIPT" \
  --output-srt "$SCRIPT_DIR/${SLUG}_word_aligned.srt" \
  --output-ass "$SCRIPT_DIR/${SLUG}_word_aligned.ass" \
  --output-json "$SCRIPT_DIR/${SLUG}_word_timings.json" \
  --report "$SCRIPT_DIR/${SLUG}_alignment_report.json" \
  --model "$MODEL"

echo
echo "Generated:"
echo "  ${SLUG}_word_aligned.srt"
echo "  ${SLUG}_word_aligned.ass"
echo "  ${SLUG}_word_timings.json"
echo "  ${SLUG}_alignment_report.json"
