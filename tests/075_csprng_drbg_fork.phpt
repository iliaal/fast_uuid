--TEST--
CSPRNG generator state (DRBG key and counter) is not inherited across fork()
--EXTENSIONS--
fast_uuid
--SKIPIF--
<?php if (!extension_loaded('pcntl')) die('skip pcntl not available'); ?>
--FILE--
<?php
declare(strict_types=1);

// Leave the 8 KiB buffer partly consumed, then fork twice. A full-buffer draw
// in each process forces exactly one refill from the generator state as of the
// fork. Equal output between parent and child means the child inherited that
// state; equal output between siblings means the reset left every child on the
// same deterministic state (every pre-forked worker minting the same UUIDs).
fast_uuid_random_bytes(16);

$children = [];
for ($i = 0; $i < 2; $i++) {
    $tmp = tempnam(sys_get_temp_dir(), 'fu_drbg');
    $pid = pcntl_fork();
    if ($pid === 0) {
        file_put_contents($tmp, fast_uuid_random_bytes(8192) . uuid_v4_bin());
        exit(0);
    }
    $children[] = [$pid, $tmp];
}

$out = ['parent' => fast_uuid_random_bytes(8192) . uuid_v4_bin()];
foreach ($children as $i => [$pid, $tmp]) {
    pcntl_waitpid($pid, $status);
    $out["child$i"] = (string) file_get_contents($tmp);
    unlink($tmp);
}

$lengthsOk = true;
foreach ($out as $bytes) {
    $lengthsOk = $lengthsOk && strlen($bytes) === 8192 + 16;
}
var_dump($lengthsOk);

// Pairwise: parent/child0, parent/child1, child0/child1. A shifted but
// otherwise shared counter would still overlap block-wise.
$names = array_keys($out);
$distinct = true;
$noSharedBlock = true;
for ($a = 0; $a < count($names); $a++) {
    for ($b = $a + 1; $b < count($names); $b++) {
        $x = $out[$names[$a]];
        $y = $out[$names[$b]];
        $distinct = $distinct && $x !== $y && substr($x, -16) !== substr($y, -16);
        $noSharedBlock = $noSharedBlock
            && count(array_intersect(str_split($x, 16), str_split($y, 16))) === 0;
    }
}
var_dump($distinct);
var_dump($noSharedBlock);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
