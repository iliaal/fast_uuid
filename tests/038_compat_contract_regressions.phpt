--TEST--
Compat contract regressions from review findings
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Uuid as CoreUuid;
use FastUuid\Compat\Uuid as CompatUuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Provider\NodeProviderInterface;
use FastUuid\Compat\Rfc4122\Fields;
use FastUuid\Compat\Validator\GenericValidator;
use FastUuid\Exception\InvalidArgumentException;
use FastUuid\Exception\UnsupportedOperationException;

function throws(callable $fn, string $class): bool {
    try { $fn(); return false; } catch (Throwable $e) { return $e instanceof $class; }
}

$base = CompatUuid::fromString('00112233-4455-4677-8899-aabbccddeeff');
$guid = new GuidStringCodec();
$factory = new UuidFactory();
$factory->setCodec($guid);

foreach ([
    [CoreUuid::NIL, CompatUuid::NIL],
    [CoreUuid::MAX, CompatUuid::MAX],
    [CoreUuid::NAMESPACE_DNS, CompatUuid::NAMESPACE_DNS],
    [CoreUuid::NAMESPACE_URL, CompatUuid::NAMESPACE_URL],
    [CoreUuid::NAMESPACE_OID, CompatUuid::NAMESPACE_OID],
    [CoreUuid::NAMESPACE_X500, CompatUuid::NAMESPACE_X500],
    [CoreUuid::DCE_DOMAIN_PERSON, CompatUuid::DCE_DOMAIN_PERSON],
    [CoreUuid::DCE_DOMAIN_GROUP, CompatUuid::DCE_DOMAIN_GROUP],
    [CoreUuid::DCE_DOMAIN_ORG, CompatUuid::DCE_DOMAIN_ORG],
] as [$coreConstant, $compatConstant]) {
    var_dump($coreConstant === $compatConstant);
}

$dnsV3 = CoreUuid::uuid3(CoreUuid::NAMESPACE_DNS, 'www.example.com')->toString();
$dnsV5 = CoreUuid::uuid5(CoreUuid::NAMESPACE_DNS, 'www.example.com')->toString();
var_dump($dnsV3 === '5df41881-3aed-3515-88a7-2f4a814cf09e');
var_dump(CompatUuid::uuid3(CompatUuid::NAMESPACE_DNS, 'www.example.com')->toString() === $dnsV3);
var_dump($dnsV5 === '2ed6657d-e927-568b-95e1-2665a8aea6a2');
var_dump(CompatUuid::uuid5(CompatUuid::NAMESPACE_DNS, 'www.example.com')->toString() === $dnsV5);

var_dump($factory->fromString($guid->encode($base))->equals($base));
var_dump($factory->fromBytes($guid->encodeBinary($base))->equals($base));

$canonical = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$canonicalCompat = CompatUuid::fromString($canonical);
var_dump(CompatUuid::fromString('urn:uuid:' . $canonical)->equals($canonicalCompat));
var_dump(CompatUuid::fromString('{' . $canonical . '}')->equals($canonicalCompat));

$validator = new GenericValidator();
var_dump($validator->validate('urn:uuid:' . $canonical));
var_dump($validator->validate('urn:uuid:{' . $canonical . '}'));
var_dump($validator->validate('urn:' . $canonical) === false);
var_dump($validator->validate('{urn:uuid:' . $canonical . '}') === false);

$ms = 1700000000000;
$v7 = CompatUuid::uuid7($ms);
var_dump($v7->getVersion() === 7);
var_dump($v7->getCore()->getTimestampMillis() === $ms);

$node = "\x01\x02\x03\x04\x05\x06";
$providerFactory = new UuidFactory();
$providerFactory->setNodeProvider(new class($node) implements NodeProviderInterface {
    public function __construct(private string $node) {}
    public function getNode(): string { return $this->node; }
});
var_dump(substr($providerFactory->uuid1()->getBytes(), 10, 6) === $node);
var_dump(substr($providerFactory->uuid2(CompatUuid::DCE_DOMAIN_PERSON, 123)->getBytes(), 10, 6) === $node);
var_dump(substr($providerFactory->fromDateTime(new DateTimeImmutable('@0'))->getBytes(), 10, 6) === $node);

$compat = CompatUuid::fromString($canonical);
$core = CoreUuid::fromString($canonical);
var_dump($compat->compareTo($core) === 0);
var_dump($compat->compareTo($canonical) === 0);
var_dump(throws(fn() => $compat->compareTo(new stdClass()), InvalidArgumentException::class));

$nonRfc = CoreUuid::fromString('00000000-0000-1000-0000-000000000000');
$fields = new Fields($nonRfc->getBytes());
var_dump($fields->getVersion() === null);
var_dump(throws(fn() => $fields->getTimestamp(), UnsupportedOperationException::class));

$badHyphen = '0011223344-5546778899-aabbccddeeff';
var_dump(CoreUuid::isValid($badHyphen) === false);
var_dump(throws(fn() => (new StringCodec())->decode($badHyphen), InvalidArgumentException::class));
var_dump(throws(fn() => (new GuidStringCodec())->decode($badHyphen), InvalidArgumentException::class));
var_dump(throws(fn() => (new TimestampFirstCombCodec())->decode($badHyphen), InvalidArgumentException::class));
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
