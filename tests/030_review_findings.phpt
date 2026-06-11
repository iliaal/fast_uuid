--TEST--
Review finding regressions: non-RFC version and 32-bit timestamp handling
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Uuid;
use FastUuid\Exception\UnsupportedOperationException;

function throws(callable $fn, string $class): bool {
    try { $fn(); return false; }
    catch (Throwable $e) { return $e instanceof $class; }
}

// The version nibble is only meaningful for RFC 9562 / RFC 4122 variants.
$nonRfc = Uuid::fromString('00000000-0000-4000-0000-000000000001');
var_dump($nonRfc->getVariant() === 0);
var_dump($nonRfc->getVersion() === null);

// v7 unix milliseconds for 2040-01-01T00:00:00Z. On 32-bit PHP this
// exercises DateTime construction beyond zend_long's timestamp range.
$future = Uuid::fromString('020251fe-2400-7000-8000-000000000000');
var_dump($future->getDateTime()->format('Y-m-d H:i:s.u') === '2040-01-01 00:00:00.000000');

// v7 unix milliseconds at 2^31, then at the 48-bit v7 ceiling. These fit in
// UUIDv7 but not in a 32-bit PHP int return value.
$overflow = Uuid::fromString('00008000-0000-7000-8000-000000000000');
$maxV7 = Uuid::fromString('ffffffff-ffff-7fff-bfff-ffffffffffff');

if (PHP_INT_SIZE >= 8) {
    var_dump($overflow->getTimestampMillis() === 2147483648);
    var_dump($maxV7->getTimestampMillis() === 281474976710655);
} else {
    var_dump(throws(fn() => $overflow->getTimestampMillis(), UnsupportedOperationException::class));
    var_dump(throws(fn() => $maxV7->getTimestampMillis(), UnsupportedOperationException::class));
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
