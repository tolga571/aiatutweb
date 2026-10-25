#!/usr/bin/env bash
# Raw recordings (scripts/media/record_home.js) → public/media/home/<scene>.mp4
# + <scene>.jpg poster. H.264, no audio, 1280x800, small enough to stream.
#   scripts/media/encode.sh /tmp/jl-rec
set -euo pipefail
IN="${1:-/tmp/jl-rec}"
OUT="$(cd "$(dirname "$0")/../.." && pwd)/public/media/home"
mkdir -p "$OUT"
for dir in "$IN"/*/; do
  name="$(basename "$dir")"
  src="$(ls "$dir"*.webm | head -1)"
  # Skip the first second (the page still loading).
  ffmpeg -loglevel error -y -ss 1 -i "$src" -an -vf "scale=1280:800:flags=lanczos,fps=24" \
    -c:v libx264 -preset slow -crf 30 -pix_fmt yuv420p -movflags +faststart "$OUT/$name.mp4"
  ffmpeg -loglevel error -y -ss 2.5 -i "$OUT/$name.mp4" -frames:v 1 -q:v 4 "$OUT/$name.jpg"
  printf '%-10s %s\n' "$name" "$(du -h "$OUT/$name.mp4" | cut -f1)"
done
