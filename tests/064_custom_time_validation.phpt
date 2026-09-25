--TEST--
Custom time generators receive only valid node and clock sequence arguments
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Provider\TimeGeneratorInterface;
use FastUuid\Compat\Uuid as CompatUuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Exception\InvalidArgumentException;

final class RecordingTimeGenerator implements TimeGeneratorInterface
{
    public array $calls = [];
    public function generate(int|string|null $node = null, ?int $clockSeq = null): string
    {
        $this->calls[] = [$node, $clockSeq];
        return (string) hex2bin('000102030405060708090a0b0c0d0e0f');
    }
}

function rejects(callable $call): bool
{
    try {
        $call();
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
}

$generator = new RecordingTimeGenerator();
$factory = new UuidFactory();
$factory->setTimeGenerator($generator);

$invalid = [
    fn() => $factory->uuid1(null, 16384),
    fn() => $factory->uuid1('bad'),
    fn() => $factory->uuid1(null, -1),
    fn() => $factory->uuid2(0, 7, null, 16384),
    fn() => $factory->uuid2(0, 7, 'bad', 0),
    fn() => $factory->uuid2(0, 7, null, -1),
    fn() => $factory->uuid6(null, 16384),
    fn() => $factory->uuid6('bad', 0),
    fn() => $factory->uuid6(null, -1),
];
foreach ($invalid as $call) {
    var_dump(rejects($call));
}
var_dump($generator->calls === []);

$factory->uuid1('ffffffffffff', 16383);
$factory->uuid2(0, 7, 'ffffffffffff', 16383);
$factory->uuid6('ffffffffffff', 16383);
var_dump($generator->calls === [
    ['ffffffffffff', 16383],
    ['ffffffffffff', 16383],
    ['ffffffffffff', 16383],
]);

CompatUuid::setFactory($factory);
var_dump(rejects(fn() => CompatUuid::uuid1('bad')));
var_dump(count($generator->calls) === 3);
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
