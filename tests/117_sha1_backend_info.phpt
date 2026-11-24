--TEST--
phpinfo() reports the SHA-1 backend used by uuid_v5()
--EXTENSIONS--
fast_uuid
--FILE--
<?php
declare(strict_types=1);

ob_start();
(new ReflectionExtension('fast_uuid'))->info();
$info = ob_get_clean();
$backend = preg_match('/^SHA-1 \(v5\) => (.+)$/m', $info, $m) === 1 ? $m[1] : '';
var_dump(preg_match('/^(SHA-NI|ARMv8 SHA-1|software( \(.+\))?)$/', $backend) === 1);
var_dump(!str_contains($backend, 'self-test failed'));

// A CPU the kernel reports as SHA-1-capable must not be detected as lacking it.
$cpuinfo = is_readable('/proc/cpuinfo') ? (string) file_get_contents('/proc/cpuinfo') : '';
$cpuHasSha1 = preg_match('/^(flags|Features)\s*:.*\b(sha_ni|sha1)\b/m', $cpuinfo) === 1;
var_dump(!$cpuHasSha1 || !str_contains($backend, 'CPU lacks'));

var_dump(uuid_v5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'www.example.com'));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
string(36) "2ed6657d-e927-568b-95e1-2665a8aea6a2"
