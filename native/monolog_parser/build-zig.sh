#!/usr/bin/env sh
# Cross-compile libmonolog_parser for all release platforms with Zig.
#
# Usage:
#   ./build-zig.sh                 # all targets → ../lib/
#   ./build-zig.sh linux-musl-arm64
#   ZIG=/path/to/zig ./build-zig.sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
OUT="${OUT:-$ROOT/../lib}"
SRC="$ROOT/src/simd_scan.c $ROOT/src/monolog_parser.c $ROOT/src/monolog_reader.c"
INC="-I$ROOT/include"
# Keep flags portable across zig targets (no host -march)
CFLAGS_COMMON="-O3 -fPIC -std=c11 -Wall -Wextra -DMONOLOG_PARSER_BUILD"

ZIG="${ZIG:-zig}"
if ! command -v "$ZIG" >/dev/null 2>&1; then
  for c in \
    "$ROOT/../../.tools/zig/zig" \
    /tmp/zig-linux-x86_64-*/zig \
    /opt/zig/zig
  do
    for hit in $c; do
      if [ -x "$hit" ]; then
        ZIG=$hit
        break 2
      fi
    done
  done
fi

if ! command -v "$ZIG" >/dev/null 2>&1 && [ ! -x "$ZIG" ]; then
  echo "zig not found — install from https://ziglang.org or set ZIG=" >&2
  exit 1
fi

echo "using: $ZIG ($("$ZIG" version 2>/dev/null || true))"
mkdir -p "$OUT"

# name|zig-target|ext
# aarch64 NEON is baseline — no extra -march needed (zig rejects gcc-style -march)
TARGETS="
linux-x86_64|x86_64-linux-gnu|so
linux-arm64|aarch64-linux-gnu|so
linux-musl-x86_64|x86_64-linux-musl|so
linux-musl-arm64|aarch64-linux-musl|so
darwin-x86_64|x86_64-macos-none|dylib
darwin-arm64|aarch64-macos-none|dylib
"

build_one() {
  name=$1
  target=$2
  ext=$3
  out="$OUT/libmonolog_parser-${name}.${ext}"

  echo "→ $name  ($target) → $out"

  # shellcheck disable=SC2086
  "$ZIG" cc \
    -target "$target" \
    -shared \
    $CFLAGS_COMMON \
    $INC \
    -o "$out" \
    $SRC

  ls -la "$out"
}

FILTER="${1:-}"

echo "$TARGETS" | while IFS= read -r line; do
  [ -z "$line" ] && continue
  name=$(echo "$line" | cut -d'|' -f1)
  target=$(echo "$line" | cut -d'|' -f2)
  ext=$(echo "$line" | cut -d'|' -f3)

  if [ -n "$FILTER" ] && [ "$FILTER" != "$name" ]; then
    continue
  fi

  build_one "$name" "$target" "$ext"
done

cp -f "$ROOT/include/monolog_parser.h" "$OUT/"

echo ""
echo "Done. Artifacts in $OUT:"
ls -la "$OUT"/libmonolog_parser-* 2>/dev/null || true
