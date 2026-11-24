--TEST--
TimestampFirstCombCodec rejects malformed canonical and bare-hex strings with the ramsey message
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Exception\InvalidUuidStringException;

$codec = new TimestampFirstCombCodec();
foreach (['6ba7b810-9dad-11d1-80b4-00c04fd430cg', '6ba7b8109dad11d180b400c04fd430cg', 'nope'] as $s) {
    try {
        $codec->decode($s);
        echo "no exception\n";
    } catch (InvalidUuidStringException $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
FastUuid\Exception\InvalidUuidStringException: Invalid UUID string
FastUuid\Exception\InvalidUuidStringException: Invalid UUID string
FastUuid\Exception\InvalidUuidStringException: Invalid UUID string
