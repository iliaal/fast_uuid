<?php
/*
 * Single-op throughput probe. Run one op in its own process so engines stay
 * isolated and the JIT/opcache state can't leak between ops:
 *   php -d extension=<so> bench/neon_bench.php <op> [iters] [runs]
 * Prints: <op> <best_ops_per_sec> <checksum>
 *
 * Loops are inlined per op (no closure dispatch) because the fast ops are
 * ~30 ns and a closure call would dominate the measurement.
 */

$op   = $argv[1] ?? 'v4_proc';
$N    = (int) ($argv[2] ?? 300000);
$RUNS = (int) ($argv[3] ?? 25);
$WARM = 20000;

if (str_starts_with($op, 'ramsey')) {
    $auto = __DIR__ . '/vendor/autoload.php';
    if (!is_file($auto)) { fwrite(STDERR, "ramsey autoload missing: $auto\n"); exit(2); }
    require $auto;
}

$bytes = hex2bin('a1b2c3d4e5f60718293a4b5c6d7e8f90'); // fixed 16 bytes for from_bin
$canon = 'a1b2c3d4-e5f6-4718-893a-4b5c6d7e8f90';      // fixed canonical for parse

$best = 0.0;
$cs   = 0;

/* warmup (untimed) */
switch ($op) {
    case 'from_bin': for ($i=0;$i<$WARM;$i++){ $s=uuid_from_bin($bytes); } break;
    case 'v4_proc':  for ($i=0;$i<$WARM;$i++){ $s=uuid_v4(); } break;
    case 'v7_proc':  for ($i=0;$i<$WARM;$i++){ $s=uuid_v7(); } break;
    case 'v4_obj':   for ($i=0;$i<$WARM;$i++){ $s=(string)\FastUuid\Uuid::uuid4(); } break;
    case 'to_bin':   for ($i=0;$i<$WARM;$i++){ $s=uuid_to_bin($canon); } break;
    case 'ramsey_v4':    for ($i=0;$i<$WARM;$i++){ $s=\Ramsey\Uuid\Uuid::uuid4()->toString(); } break;
    case 'ramsey_v7':    for ($i=0;$i<$WARM;$i++){ $s=\Ramsey\Uuid\Uuid::uuid7()->toString(); } break;
    case 'ramsey_parse': for ($i=0;$i<$WARM;$i++){ $s=\Ramsey\Uuid\Uuid::fromString($canon)->getBytes(); } break;
    default: fwrite(STDERR, "unknown op: $op\n"); exit(2);
}

for ($r = 0; $r < $RUNS; $r++) {
    $t = hrtime(true);
    switch ($op) {
        case 'from_bin': for ($i=0;$i<$N;$i++){ $s=uuid_from_bin($bytes); $cs+=$s[0]==='a'?1:0; } break;
        case 'v4_proc':  for ($i=0;$i<$N;$i++){ $s=uuid_v4();             $cs+=$s[14]==='4'?1:0; } break;
        case 'v7_proc':  for ($i=0;$i<$N;$i++){ $s=uuid_v7();             $cs+=$s[14]==='7'?1:0; } break;
        case 'v4_obj':   for ($i=0;$i<$N;$i++){ $s=(string)\FastUuid\Uuid::uuid4(); $cs+=$s[14]==='4'?1:0; } break;
        case 'to_bin':   for ($i=0;$i<$N;$i++){ $s=uuid_to_bin($canon);   $cs+=$s[0]==="\xa1"?1:0; } break;
        case 'ramsey_v4':    for ($i=0;$i<$N;$i++){ $s=\Ramsey\Uuid\Uuid::uuid4()->toString(); $cs+=$s[14]==='4'?1:0; } break;
        case 'ramsey_v7':    for ($i=0;$i<$N;$i++){ $s=\Ramsey\Uuid\Uuid::uuid7()->toString(); $cs+=$s[14]==='7'?1:0; } break;
        case 'ramsey_parse': for ($i=0;$i<$N;$i++){ $s=\Ramsey\Uuid\Uuid::fromString($canon)->getBytes(); $cs+=$s[0]==="\xa1"?1:0; } break;
    }
    $dt  = (hrtime(true) - $t) / 1e9;
    $ops = $N / $dt;
    if ($ops > $best) $best = $ops;
}

printf("%-14s %12.0f  cs=%d\n", $op, $best, $cs);
