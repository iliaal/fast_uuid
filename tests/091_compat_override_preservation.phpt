--TEST--
compat: fast paths keep honouring subclass overrides of getCore(), toString(), getCodec() and isCanonicalShape()
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\AbstractUuid;
use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Guid\Guid;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\UuidInterface;
use FastUuid\Compat\Validator\GenericValidator;
use FastUuid\Compat\Validator\NonstandardValidator;

// An open AbstractUuid subclass that skips the parent constructor and reports a
// different core from getCore(); every internal reader must go through it.
final class ShadowCoreUuid extends AbstractUuid
{
    public static int $calls = 0;

    public function __construct(\FastUuid\Uuid $core, private \FastUuid\Uuid $shadow)
    {
        $this->core = $core;
    }

    public function getCore(): \FastUuid\Uuid
    {
        self::$calls++;
        return $this->shadow;
    }
}

// A subclass overriding toString(): the inherited __toString() must follow it.
final class LabelledUuid extends AbstractUuid
{
    public function __construct(\FastUuid\Uuid $core)
    {
        $this->core = $core;
    }

    public function toString(): string
    {
        return 'label:' . parent::toString();
    }
}

$seen = \FastUuid\Uuid::uuid4();
$shadow = \FastUuid\Uuid::uuid4();
$plain = Uuid::fromString($seen->toString());
$shadowed = new ShadowCoreUuid($seen, $shadow);

// getCore() override: honoured by equals/compareTo and by coreFrom() callers.
ShadowCoreUuid::$calls = 0;
var_dump($plain->equals($shadowed) === false);
var_dump(ShadowCoreUuid::$calls === 1);
var_dump($plain->compareTo($shadowed) !== 0);
var_dump(ShadowCoreUuid::$calls === 2);
var_dump(Uuid::fromString($shadow->toString())->equals($shadowed));
var_dump(Uuid::fromString($shadow->toString())->compareTo($shadowed) === 0);
ShadowCoreUuid::$calls = 0;
var_dump((new StringCodec())->encodeBinary($shadowed) === $shadow->getBytes());
var_dump((new GuidStringCodec())->encodeBinary($shadowed) === GuidStringCodec::swap($shadow->getBytes()));
var_dump((new TimestampFirstCombCodec())->encodeBinary($shadowed)
    === (new TimestampFirstCombCodec())->encodeBinary(Uuid::fromString($shadow->toString())));
var_dump((new Guid($shadowed))->getBytes() === GuidStringCodec::swap($shadow->getBytes()));
var_dump(ShadowCoreUuid::$calls === 4);
var_dump(Uuid::uuid5($shadowed, 'x')->getCore()->equals(\FastUuid\Uuid::uuid5($shadow, 'x')));
var_dump(Uuid::uuid3($shadowed, 'x')->getCore()->equals(\FastUuid\Uuid::uuid3($shadow, 'x')));

// A Guid resolves to its inner UUID's core, recursively.
$inner = new Guid(new Guid($shadowed));
var_dump((new StringCodec())->encodeBinary($inner) === $shadow->getBytes());

// toString() override: the cast, the method and the JSON form agree.
$labelled = new LabelledUuid($seen);
var_dump((string) $labelled === 'label:' . $seen->toString());
var_dump($labelled->toString() === (string) $labelled);
var_dump(json_encode($labelled) === json_encode((string) $labelled));

// In-tree final wrappers: the inlined __toString() matches toString() under
// every codec shape.
$factories = [new UuidFactory(), new UuidFactory(), new UuidFactory(), new UuidFactory()];
$factories[1]->setCodec(new GuidStringCodec());
$factories[2]->setCodec(new TimestampFirstCombCodec());
$factories[3]->setCodec(new StringCodec());
foreach ($factories as $index => $factory) {
    foreach ([$factory->uuid4(), $factory->uuid7(), $factory->fromString('00000000-0000-0000-0000-000000000000'),
        $factory->fromString('ffffffff-ffff-ffff-ffff-ffffffffffff'),
        $factory->fromString('00112233-4455-4677-c899-aabbccddeeff')] as $uuid) {
        var_dump((string) $uuid === $uuid->toString());
        // The COMB codec presents field-permuted text by design.
        var_dump($index === 2 || $uuid->toString() === $uuid->getCore()->toString());
    }
}

// A recording codec on an open UuidFactory subclass: a string namespace must
// still reach the overriding getCodec() rather than the direct-parse shortcut.
final class RecordingCodec extends StringCodec
{
    public int $decoded = 0;

    public function decode(string $encoded): UuidInterface
    {
        $this->decoded++;
        return parent::decode($encoded);
    }
}

final class CodecOverridingFactory extends UuidFactory
{
    public RecordingCodec $recording;

    public function __construct()
    {
        $this->recording = new RecordingCodec();
    }

    public function getCodec(): \FastUuid\Compat\Codec\CodecInterface
    {
        return $this->recording;
    }
}

$overriding = new CodecOverridingFactory();
$dns = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
var_dump($overriding->uuid5($dns, 'x')->getCore()->equals(\FastUuid\Uuid::uuid5($dns, 'x')));
var_dump($overriding->recording->decoded === 1);
var_dump($overriding->uuid3($dns, 'x')->getCore()->equals(\FastUuid\Uuid::uuid3($dns, 'x')));
var_dump($overriding->recording->decoded === 2);

// A GenericValidator subclass that accepts a 34-byte inner shape: a braced
// 36-byte candidate must still be unwrapped before that check.
final class ShortShapeValidator extends GenericValidator
{
    protected static function isCanonicalShape(string $inner): bool
    {
        return \strlen($inner) === 34 && \strspn($inner, '0123456789abcdef') === 34;
    }
}

$inner34 = 'aaaaaaaaaaaaaa4aaaa8aaaaaaaaaaaaaa';
$braced = '{' . $inner34 . '}';
var_dump(\strlen($braced) === 36);
var_dump((new ShortShapeValidator())->validate($braced) === true);
var_dump((new ShortShapeValidator())->validate($inner34) === true);
var_dump((new GenericValidator())->validate($braced) === false);
var_dump((new NonstandardValidator())->validate($braced) === false);
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
