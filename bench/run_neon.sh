#!/usr/bin/env bash
# Drive the NEON-vs-scalar A/B plus a ramsey/uuid headline on this ARM box.
# Usage: run_neon.sh <neon.so> <scalar.so> [core] [iters] [runs]
set -u
NEON="$1"; SCALAR="$2"; CORE="${3:-3}"; ITERS="${4:-300000}"; RUNS="${5:-25}"
PHP=php
HARNESS="$(dirname "$0")/neon_bench.php"
PIN="taskset -c $CORE"

run() { $PIN "$PHP" -d extension="$1" "$HARNESS" "$2" "$ITERS" "$RUNS"; }

echo "== host =="; uname -m; (lscpu | grep -i 'model name' | head -1)
echo "core=$CORE iters=$ITERS runs=$RUNS (best-of)"
echo
echo "== formatter A/B (RNG shared; delta = NEON formatter) =="
printf '%-14s %14s %14s\n' op NEON scalar
for op in from_bin v4_proc v7_proc v4_obj to_bin; do
  n=$(run "$NEON"   "$op" | awk '{print $2}')
  s=$(run "$SCALAR" "$op" | awk '{print $2}')
  printf '%-14s %14s %14s\n' "$op" "$n" "$s"
done
echo
echo "== NEON build vs ramsey/uuid =="
for op in v4_proc v4_obj v7_proc to_bin ramsey_v4 ramsey_v7 ramsey_parse; do
  run "$NEON" "$op"
done
