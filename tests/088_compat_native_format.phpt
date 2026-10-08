--TEST--
Compat codec formatting preserves all byte values, storage layouts, and length errors
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\OrderedTimeCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Codec\TimestampLastCombCodec;
use FastUuid\Exception\InvalidArgumentException;

class FormatProbe extends StringCodec
{
    public static function format(string $bytes): string
    {
        return parent::bytesToString($bytes);
    }
}

function referenceFormat(string $bytes): string
{
    $h = bin2hex($bytes);
    return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4)
        . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
}

$matches = true;
for ($value = 0; $value < 256; ++$value) {
    for ($offset = 0; $offset < 16; ++$offset) {
        $bytes = str_repeat("\0", 16);
        $bytes[$offset] = chr($value);
        $matches = $matches && FormatProbe::format($bytes) === referenceFormat($bytes);
    }
}
var_dump($matches);

foreach ([
    str_repeat("\0", 16),
    str_repeat("\xff", 16),
    hex2bin('00112233445516778899aabbccddeeff'),
    hex2bin('001122334455f677c899aabbccddeeff'),
] as $bytes) {
    $uuid = (new StringCodec())->decodeBytes($bytes);
    $expected = referenceFormat($bytes);
    foreach ([new StringCodec(), new GuidStringCodec(), new OrderedTimeCodec(), new TimestampLastCombCodec()] as $codec) {
        var_dump($codec->encode($uuid) === $expected);
    }
    $comb = new TimestampFirstCombCodec();
    $swapped = substr($bytes, 10, 6) . substr($bytes, 6, 4) . substr($bytes, 0, 6);
    var_dump($comb->encode($uuid) === referenceFormat($swapped));
    var_dump($comb->decode($comb->encode($uuid))->getCore()->getBytes() === $bytes);
}

foreach ([0, 1, 15, 17, 32] as $length) {
    try {
        FormatProbe::format(str_repeat("\0", $length));
        var_dump(false);
    } catch (InvalidArgumentException $e) {
        var_dump(get_class($e) === InvalidArgumentException::class && $e->getMessage() === 'Expected 16 bytes');
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
