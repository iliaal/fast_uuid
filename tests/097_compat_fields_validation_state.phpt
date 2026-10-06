--TEST--
compat: rejected Fields restoration preserves the previous valid value
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Compat\Nonstandard\Fields as NonstandardFields;
use FastUuid\Exception\InvalidArgumentException;

$original = hex2bin('00112233445546778899aabbccddeeff');
$replacement = hex2bin('ffeeddccbbaa49888766554433221100');
$invalid = ['', 'short', str_repeat('x', 17)];
foreach ([Fields::class, NonstandardFields::class] as $class) {
    $payloads = $invalid;
    if ($class === Fields::class) {
        $payloads[] = hex2bin('0011223344554677c899aabbccddeeff');
        $payloads[] = hex2bin('00112233445596778899aabbccddeeff');
    }
    foreach (['__construct', 'unserialize', '__unserialize'] as $method) {
        $fields = new $class($original);
        foreach ($payloads as $payload) {
            // Legacy restoration accepts either raw bytes or base64 bytes.
            $inputs = $method === '__construct' ? [$payload] : [$payload, base64_encode($payload)];
            foreach ($inputs as $input) {
                try {
                    $fields->$method($method === '__unserialize' ? ['bytes' => $input] : $input);
                    echo "accepted invalid input\n";
                } catch (InvalidArgumentException) {
                    if ($fields->getBytes() !== $original || $fields->serialize() !== $original) {
                        echo "changed after rejection\n";
                    }
                }
            }
        }
        var_dump($fields->getBytes() === $original);
        $fields->$method($method === '__unserialize' ? ['bytes' => $replacement] : $replacement);
        var_dump($fields->getBytes() === $replacement);
        if ($method !== '__construct') {
            $encoded = base64_encode($original);
            $fields->$method($method === '__unserialize' ? ['bytes' => $encoded] : $encoded);
            var_dump($fields->getBytes() === $original);
        }
    }
}
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
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
