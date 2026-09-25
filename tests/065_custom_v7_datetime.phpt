--TEST--
Custom-random UUIDv7 preserves core DateTime and platform-width timestamp semantics
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Provider\RandomGeneratorInterface;
use FastUuid\Compat\Uuid as CompatUuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Exception\InvalidArgumentException;

final class FixedTenBytes implements RandomGeneratorInterface
{
    public function generate(int $length): string
    {
        return str_repeat("\x5a", $length);
    }
}

function rejectsV7(callable $call): bool
{
    try {
        $call();
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
}

$factory = new UuidFactory();
$factory->setRandomGenerator(new FixedTenBytes());
$default = new UuidFactory();
CompatUuid::setFactory($factory);

$badMicroseconds = new class('@1700000000.123456') extends DateTimeImmutable {
    public function format(string $format): string
    {
        return $format === 'u' ? '1000000' : parent::format($format);
    }
};
$badTimestamp = new class('@1700000000.123456') extends DateTimeImmutable {
    public function getTimestamp(): int { return -1; }
};

foreach ([$badMicroseconds, $badTimestamp] as $dateTime) {
    var_dump(rejectsV7(fn() => FastUuid\Uuid::uuid7($dateTime)));
    var_dump(rejectsV7(fn() => $default->uuid7($dateTime)));
    var_dump(rejectsV7(fn() => $factory->uuid7($dateTime)));
    var_dump(rejectsV7(fn() => CompatUuid::uuid7($dateTime)));
}

$valid = new DateTimeImmutable('@1700000000.123456');
$coreBytes = FastUuid\Uuid::uuid7($valid)->getBytes();
var_dump(substr($factory->uuid7($valid)->getBytes(), 0, 6) === substr($coreBytes, 0, 6));
var_dump(substr(CompatUuid::uuid7($valid)->getBytes(), 0, 6) === substr($coreBytes, 0, 6));

$subclass = new class('@1.123456') extends DateTimeImmutable {};
var_dump(substr($factory->uuid7($subclass)->getBytes(), 0, 6) === substr(FastUuid\Uuid::uuid7($subclass)->getBytes(), 0, 6));

// getDateTime()->format('U') remains a decimal string even on 32-bit PHP.
$before = (new DateTimeImmutable())->format('U');
$current = $factory->uuid7();
$after = (new DateTimeImmutable())->format('U');
$actual = $current->getDateTime()->format('U');
$decimalCompare = static function (string $left, string $right): int {
    return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
};
var_dump($decimalCompare($before, $actual) <= 0);
var_dump($decimalCompare($actual, $after) <= 0);

if (PHP_INT_SIZE >= 8) {
    $max = 0xffffffffffff;
    var_dump(substr($factory->uuid7($max)->getBytes(), 0, 6) === substr(FastUuid\Uuid::uuid7($max)->getBytes(), 0, 6));
    var_dump(rejectsV7(fn() => $factory->uuid7(-1)));
    var_dump(rejectsV7(fn() => $factory->uuid7($max + 1)));
}
else {
    $future = new DateTimeImmutable('@281474976710.123456');
    $coreFuture = FastUuid\Uuid::uuid7($future)->getBytes();
    $customFuture = $factory->uuid7($future)->getBytes();
    var_dump(substr($customFuture, 0, 6) === substr($coreFuture, 0, 6));
    var_dump($future->format('U') === '281474976710');
    var_dump(PHP_INT_SIZE === 4);
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
