--TEST--
C-vs-compat decode-vector mirror incl. version nibbles (CR-020)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Exception\InvalidArgumentException;
use FastUuid\Uuid as CoreUuid;

function fieldsReject(string $bytes): bool {
    try {
        new Fields($bytes);
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
}

// [canonical string, expected version (null for nil/max/non-RFC), expected variant]
$vectors = [
    ['6ba7b810-9dad-11d1-80b4-00c04fd430c8', 1, 2],
    ['5df41881-3aed-3515-88a7-2f4a814cf09e', 3, 2],
    ['f47ac10b-58cc-4372-a567-0e02b2c3d479', 4, 2],
    ['2ed6657d-e927-568b-95e1-2665a8aea6a2', 5, 2],
    ['6ba7b810-9dad-61d1-80b4-00c04fd430c8', 6, 2],
    ['00000000-0000-7000-8000-000000000000', 7, 2],
    ['00000000-0000-8000-8000-000000000000', 8, 2],
    [CoreUuid::NIL, null, 0],
    [CoreUuid::MAX, null, 7],
    ['00000000-0000-1000-0000-000000000000', null, 0],
    ['00000000-0000-1000-c000-000000000000', null, 6],
];

$sc = new StringCodec();
foreach ($vectors as [$s, $ver, $var]) {
    $core = CoreUuid::fromString($s);
    var_dump(CoreUuid::isValid($s));
    var_dump($core->getVersion() === $ver);
    var_dump($core->getVariant() === $var);
    // The compat canonical codec decodes to the same core bytes.
    $dec = $sc->decode($s);
    var_dump($dec->getCore()->equals($core));
    // Version/variant survive the layer crossing both ways.
    var_dump($dec->getVersion() === $ver);
    var_dump($dec->getVariant() === $var);
    if ($ver !== null || $s === CoreUuid::NIL || $s === CoreUuid::MAX) {
        $f = new Fields($core->getBytes());
        var_dump($f->getVersion() === $ver);
        var_dump($f->getVariant() === $var);
    } else {
        // Non-RFC variants carry no version: core reports null, compat Fields rejects.
        var_dump(fieldsReject($core->getBytes()));
        var_dump($core->getVersion() === null);
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
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
