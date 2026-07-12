--TEST--
Compat string codecs throw InvalidUuidStringException for malformed UUID text
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\OrderedTimeCodec;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Codec\TimestampLastCombCodec;
use FastUuid\Exception\InvalidUuidStringException;

foreach ([
    new StringCodec(),
    new GuidStringCodec(),
    new OrderedTimeCodec(),
    new TimestampFirstCombCodec(),
    new TimestampLastCombCodec(),
] as $codec) {
    try {
        $codec->decode('bad');
        var_dump(false);
    } catch (Throwable $e) {
        var_dump(get_class($e) === InvalidUuidStringException::class);
    }
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
