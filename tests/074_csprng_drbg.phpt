--TEST--
CSPRNG backend is reported, and output stays sane across refill, streaming, and reseed boundaries
--EXTENSIONS--
fast_uuid
--FILE--
<?php
declare(strict_types=1);

ob_start();
(new ReflectionExtension('fast_uuid'))->info();
$info = ob_get_clean();
$backend = preg_match('/^CSPRNG => (.+)$/m', $info, $m) === 1 ? $m[1] : '';
var_dump(str_starts_with($backend, 'AES-256-CTR DRBG (')
    || str_starts_with($backend, 'OS CSPRNG (php_random_bytes)'));
var_dump(!str_contains($backend, 'self-test failed'));

// A CPU the kernel reports as AES-capable must not be detected as lacking it.
$cpuinfo = is_readable('/proc/cpuinfo') ? (string) file_get_contents('/proc/cpuinfo') : '';
$cpuHasAes = preg_match('/^(flags|Features)\s*:.*\baes\b/m', $cpuinfo) === 1;
var_dump(!$cpuHasAes || !str_contains($backend, 'CPU lacks'));

// 1 MiB in one call streams past the buffer: 128 per-key chunks, 16 reseeds.
$big = fast_uuid_random_bytes(1 << 20);
var_dump(strlen($big) === 1 << 20);
// A stuck key or counter repeats keystream blocks.
$blocks = str_split($big, 16);
var_dump(count(array_unique($blocks)) === count($blocks));
// Each byte value within 6 sigma of uniform (4096 +- 384 per value).
$counts = count_chars($big, 1);
var_dump(count($counts) === 256 && max($counts) < 4096 + 384 && min($counts) > 4096 - 384);

// Buffered small draws across ~120 refills and several reseeds.
$small = '';
for ($i = 0; $i < 20000; $i++) {
    $small .= fast_uuid_random_bytes(48);
}
$blocks = str_split($small, 16);
var_dump(count(array_unique($blocks)) === count($blocks));

// Streaming lengths that end mid-block and mid-chunk.
$ok = true;
foreach ([8193, 8207, 65537, 65536 + 8192 + 1] as $n) {
    $a = fast_uuid_random_bytes($n);
    $b = fast_uuid_random_bytes($n);
    $ok = $ok && strlen($a) === $n && strlen($b) === $n && $a !== $b
        && substr($a, -16) !== substr($b, -16);
}
var_dump($ok);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
