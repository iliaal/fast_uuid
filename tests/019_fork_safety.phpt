--TEST--
CSPRNG buffer and xoshiro state are not shared across fork() (CR-001)
--EXTENSIONS--
fast_uuid
--SKIPIF--
<?php if (!extension_loaded('pcntl')) die('skip pcntl not available'); ?>
--FILE--
<?php
declare(strict_types=1);

$tmp = tempnam(sys_get_temp_dir(), 'fu_fork');

// Prime the batched CSPRNG buffer, seed the non-crypto PRNG, and advance the
// v7 monotonic state in the parent.
uuid_v4();
uuid_v4_fast();
uuid_v7();

$pid = pcntl_fork();
if ($pid === 0) {
    // Child: fork-unaware state would hand back the same bytes / same v7
    // (key, rand_b) the parent is about to draw. Write what the child sees.
    file_put_contents($tmp, uuid_v4() . "\n" . uuid_v4_fast() . "\n" . uuid_v7());
    exit(0);
}

pcntl_waitpid($pid, $status);
$parentCrypto = uuid_v4();
$parentFast   = uuid_v4_fast();
$parentV7     = uuid_v7();
[$childCrypto, $childFast, $childV7] = explode("\n", file_get_contents($tmp));
unlink($tmp);

var_dump($parentCrypto !== $childCrypto); // crypto buffer invalidated in the child
var_dump($parentFast   !== $childFast);   // xoshiro reseeded in the child
var_dump($parentV7     !== $childV7);     // v7 monotonic state reset in the child
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
