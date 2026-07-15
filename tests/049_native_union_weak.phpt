--TEST--
native unions preserve weak scalar coercion before domain validation
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Exception\InvalidUuidStringException;
use FastUuid\Uuid;

try {
    $v1 = Uuid::uuid1(true, 0);
    var_dump(substr($v1->getBytes(), 10) === hex2bin('000000000001'));
} catch (Throwable) {
    var_dump(false);
}

try {
    $v6 = Uuid::uuid6(false, 0);
    var_dump(substr($v6->getBytes(), 10) === hex2bin('000000000000'));
} catch (Throwable) {
    var_dump(false);
}

try {
    $v2 = Uuid::uuid2(Uuid::DCE_DOMAIN_ORG, true, false, 0);
    var_dump(unpack('N', substr($v2->getBytes(), 0, 4))[1] === 1);
    var_dump(substr($v2->getBytes(), 10) === hex2bin('000000000000'));
} catch (Throwable) {
    var_dump(false);
    var_dump(false);
}

try {
    Uuid::fromHexadecimal(true);
    var_dump(false);
} catch (Throwable $e) {
    var_dump($e::class === InvalidUuidStringException::class);
}

try {
    Uuid::uuid1([]);
    var_dump(false);
} catch (Throwable $e) {
    var_dump($e::class === TypeError::class);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
