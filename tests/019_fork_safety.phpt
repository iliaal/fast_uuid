--TEST--
CSPRNG buffer and xoshiro state are not shared across fork() (CR-001)
--EXTENSIONS--
fast_uuid
--SKIPIF--
<?php if (!extension_loaded('pcntl')) die('skip pcntl not available'); ?>
--FILE--
<?php
declare(strict_types=1);

// 60-bit v7 time key (unix_ms << 12 | rand_a) as 15 hex nibbles.
function v7key(string $uuid): string {
    $h = str_replace('-', '', $uuid);
    return substr($h, 0, 12) . substr($h, 13, 3);
}

// Top 48 bits of the 62-bit rand_b counter, as hex. Two independent draws agree
// here with probability 2^-48; a counter inherited across fork() agrees exactly.
function v7randbHigh(string $uuid): string {
    $h = str_replace('-', '', $uuid);
    return sprintf('%02x', hexdec(substr($h, 16, 2)) & 0x3f) . substr($h, 18, 10);
}

$tmp = tempnam(sys_get_temp_dir(), 'fu_fork');

// Prime the batched CSPRNG buffer, seed the non-crypto PRNG, and advance the
// v7 monotonic state in the parent.
uuid_v4();
uuid_v4_fast();
$primedV7 = uuid_v7();

$pid = pcntl_fork();
if ($pid === 0) {
    // Child: fork-unaware state would hand back the same bytes / same v7
    // (key, rand_b) the parent is about to draw. Write what the child sees.
    file_put_contents($tmp, implode("\n", [uuid_v4(), uuid_v4_fast(), uuid_v7(), uuid_v7()]));
    exit(0);
}

pcntl_waitpid($pid, $status);
$parentCrypto = uuid_v4();
$parentFast   = uuid_v4_fast();
$parentV7     = uuid_v7();
[$childCrypto, $childFast, $childV7, $childV7b] = explode("\n", file_get_contents($tmp));
unlink($tmp);

var_dump($parentCrypto !== $childCrypto); // crypto buffer invalidated in the child
var_dump($parentFast   !== $childFast);   // xoshiro reseeded in the child

// The child must not continue the parent's (key, rand_b) sequence. An inherited
// counter only produces a literal duplicate when both processes generate inside
// the same 244 ns key bucket, which userland cannot force -- so assert the
// signature rather than the equality: identical 60-bit key plus a rand_b drawn
// from the parent's base. `$parentV7 !== $childV7` alone is a false green; it
// still passes with the v7 reset compiled out of the atfork handler.
var_dump(!(v7key($primedV7) === v7key($childV7)
    && v7randbHigh($primedV7) === v7randbHigh($childV7)));
var_dump(!(v7key($parentV7) === v7key($childV7)
    && v7randbHigh($parentV7) === v7randbHigh($childV7)));
var_dump($parentV7 !== $childV7);
// v7 state is healthy after the reset: still unique and ordered in the child.
var_dump($childV7b > $childV7);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
