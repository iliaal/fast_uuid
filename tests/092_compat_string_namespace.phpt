--TEST--
compat: string namespaces preserve canonical identity and explicit factory parsing overrides
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\UuidInterface;
use FastUuid\Exception\InvalidArgumentException;
use FastUuid\Exception\InvalidUuidStringException;

final class CodecPathFactory extends UuidFactory {}

final class ThrowingFactory extends UuidFactory
{
    public function fromString(string $uuid): UuidInterface
    {
        throw new InvalidArgumentException('factory refused');
    }
}

function outcome(callable $call): string
{
    try {
        return $call()->getCore()->toString();
    } catch (Throwable $e) {
        return get_class($e) . '|' . $e->getMessage();
    }
}

$dns = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$inputs = [
    $dns,
    strtoupper($dns),
    'urn:uuid:' . $dns,
    '{' . $dns . '}',
    str_replace('-', '', $dns),
    '00000000-0000-0000-0000-000000000000',
    'ffffffff-ffff-ffff-ffff-ffffffffffff',
    '',
    'not-a-uuid',
    $dns . "\n",
    ' ' . $dns,
    str_repeat('a', 300),
];

$fresh = new UuidFactory();
$materialised = new UuidFactory();
$materialised->getCodec();
$explicit = new UuidFactory();
$explicit->setCodec(new StringCodec());
$reference = new CodecPathFactory();

$agree = true;
$valid = 0;
foreach ($inputs as $ns) {
    foreach (['uuid3', 'uuid5'] as $method) {
        $expected = outcome(static fn() => $reference->$method($ns, 'name'));
        foreach ([$fresh, $materialised, $explicit] as $factory) {
            $agree = $agree && outcome(static fn() => $factory->$method($ns, 'name')) === $expected;
        }
        $valid += (int) (strpos($expected, '|') === false);
    }
}
var_dump($agree);
var_dump($valid > 0 && $valid < count($inputs) * 2);

// Same answers as the core, through the facade after its factory parsed once.
Uuid::fromString($dns);
var_dump(Uuid::uuid5($dns, 'www.example.com')->toString() === \FastUuid\Uuid::uuid5($dns, 'www.example.com')->toString());
var_dump(Uuid::uuid3($dns, 'www.example.com')->toString() === \FastUuid\Uuid::uuid3($dns, 'www.example.com')->toString());

// Bad namespaces keep their exception type.
foreach ([$fresh, $materialised, $explicit, $reference] as $factory) {
    try {
        $factory->uuid5('not-a-uuid', 'x');
        var_dump(false);
    } catch (InvalidUuidStringException) {
        var_dump(true);
    }
}

// Output presentation never changes the namespace string's identity.
$comb = new UuidFactory();
$comb->setCodec(new TimestampFirstCombCodec());
$combText = (new TimestampFirstCombCodec())->encode(Uuid::fromString($dns));
var_dump($comb->uuid5($combText, 'x')->getCore()->equals(\FastUuid\Uuid::uuid5($combText, 'x')));
$guid = new UuidFactory();
$guid->setCodec(new GuidStringCodec());
var_dump($guid->uuid5($dns, 'x')->getCore()->equals(\FastUuid\Uuid::uuid5($dns, 'x')));

// A factory parsing override's failure other than a bad string is still wrapped.
$throwing = new ThrowingFactory();
try {
    $throwing->uuid5($dns, 'x');
    var_dump(false);
} catch (InvalidArgumentException $e) {
    var_dump(!$e instanceof InvalidUuidStringException);
    var_dump($e->getMessage() === 'Invalid namespace');
    var_dump($e->getPrevious()?->getMessage() === 'factory refused');
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
