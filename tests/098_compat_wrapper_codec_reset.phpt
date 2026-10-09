--TEST--
compat: explicit wrapper construction replaces or clears the previous codec
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Codec\TimestampLastCombCodec;
use FastUuid\Compat\Internal\WrapperClass;
use FastUuid\Compat\UuidInterface;
use FastUuid\Exception\InvalidArgumentException;
use FastUuid\Uuid;

class CustomStringCodec extends StringCodec
{
    public function encode(UuidInterface $uuid): string
    {
        return strtoupper(parent::encode($uuid));
    }
}

function snapshot(UuidInterface $uuid): array
{
    return [
        $uuid->getBytes(), $uuid->toString(), (string) $uuid, $uuid->getUrn(),
        (string) $uuid->getHex(), (string) $uuid->getInteger(),
        $uuid->jsonSerialize(), $uuid->serialize(), $uuid->__serialize(),
    ];
}

$codecs = [null, new StringCodec(), new CustomStringCodec(),
    new TimestampFirstCombCodec(), new GuidStringCodec(), new TimestampLastCombCodec()];
$values = [Uuid::NIL, Uuid::MAX, '00112233-4455-9677-c899-aabbccddeeff'];
foreach (range(1, 8) as $version) {
    $values[] = "00112233-4455-{$version}677-8899-aabbccddeeff";
}
foreach ($values as $value) {
    $core = Uuid::fromString($value);
    $class = WrapperClass::for($core);
    $ok = true;
    foreach ($codecs as $previous) {
        foreach ($codecs as $next) {
            $uuid = new $class($core, $previous);
            $uuid->__construct($core, $next);
            $ok = $ok && snapshot($uuid) === snapshot(new $class($core, $next));
        }
        $uuid = new $class($core, $previous);
        $uuid->__construct($core);
        $ok = $ok && snapshot($uuid) === snapshot(new $class($core));

        // A mismatched core must leave both identity and presentation intact.
        $uuid = new $class($core, $previous);
        $before = snapshot($uuid);
        $wrong = Uuid::fromString($value === Uuid::NIL ? Uuid::MAX : Uuid::NIL);
        try {
            $uuid->__construct($wrong, new TimestampFirstCombCodec());
            $ok = false;
        } catch (InvalidArgumentException) {
            $ok = $ok && snapshot($uuid) === $before;
        }
    }
    var_dump($ok);
}

// Replacement updates the core as well as clearing its old presentation.
$first = Uuid::fromString('00112233-4455-4677-8899-aabbccddeeff');
$second = Uuid::fromString('ffeeddcc-bbaa-4988-8766-554433221100');
$class = WrapperClass::for($first);
$uuid = new $class($first, new TimestampFirstCombCodec());
$uuid->__construct($second);
var_dump($uuid->getCore() === $second);
var_dump(snapshot($uuid) === snapshot(new $class($second)));
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
