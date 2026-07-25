--TEST--
Review fixes: codec identity, serialize bytes, custom v7 RNG, ConstructionToken, name cap
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\Codec\GuidStringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Internal\ConstructionToken;
use FastUuid\Compat\Rfc4122\UuidV4;
use FastUuid\Compat\Provider\RandomGeneratorInterface;
use FastUuid\Compat\Provider\TimeGeneratorInterface;
use FastUuid\Exception\InvalidArgumentException;

// --- Guid/COMB identity stays network-order (CR-001) ---
$fixed = Uuid::fromString('00112233-4455-4677-8899-aabbccddeeff');
$gf = new UuidFactory();
$gf->setCodec(new GuidStringCodec());
var_dump($gf->fromHexadecimal($fixed->getHex())->equals($fixed));
var_dump($gf->fromInteger((string) $fixed->getInteger())->equals($fixed));
$g4 = $gf->uuid4();
var_dump($g4->getHex()->toString() === $g4->getCore()->getHex());
var_dump((string) $g4->getInteger() === $g4->getCore()->getInteger());

$cf = new UuidFactory();
$cf->setCodec(new TimestampFirstCombCodec());
$c4 = $cf->uuid4();
var_dump($c4->getHex()->toString() === $c4->getCore()->getHex());
var_dump($cf->fromInteger((string) $c4->getCore()->getInteger())->equals($c4));

// --- serialize persists 16 network bytes; cross-codec restore works (CR-002) ---
// fromBytes keeps network identity under Guid codec (fromString of RFC text would swap).
Uuid::setFactory($gf);
$ser = serialize($gf->fromBytes($fixed->getBytes()));
Uuid::setFactory(new UuidFactory());
$back = unserialize($ser);
var_dump($back->equals($fixed));
var_dump(strlen($fixed->serialize()) === 16);

// --- ConstructionToken::Trusted no longer skips assert (CR-003) ---
$v1 = Uuid::uuid1();
$threw = false;
try {
    new UuidV4($v1->getCore(), null, ConstructionToken::Trusted);
} catch (InvalidArgumentException) {
    $threw = true;
}
var_dump($threw);

// --- custom RandomGenerator feeds uuid7 rand bits (CR-004) ---
class FixedTen implements RandomGeneratorInterface {
    public function __construct(private string $bytes) {}
    public function generate(int $length): string {
        if ($length === 16) {
            return str_repeat("\x22", 16);
        }
        return substr(str_repeat($this->bytes, (int) ceil($length / strlen($this->bytes))), 0, $length);
    }
}
$rf = new UuidFactory();
$rf->setRandomGenerator(new FixedTen("\xaa\xbb\xcc\xdd\xee\xff\x11\x22\x33\x44"));
$a = $rf->uuid7(0);
$b = $rf->uuid7(0);
// same ms + same custom rand => identical v7
var_dump($a->equals($b));
var_dump($a->getVersion() === 7);
// uuid4 still uses the custom generator (version/variant bits applied on top)
$u4b = $rf->uuid4()->getBytes();
var_dump($u4b[0] === "\x22" && (ord($u4b[6]) & 0xf0) === 0x40 && (ord($u4b[8]) & 0xc0) === 0x80);

// --- custom TimeGenerator feeds uuid2 (CR-007) ---
class FixedTime implements TimeGeneratorInterface {
    public function generate($node = null, ?int $clockSeq = null): string {
        // v1-shaped layout with recognizable time_mid/time_hi
        return hex2bin('00000000111122228000010203040506');
    }
}
$tf = new UuidFactory();
$tf->setTimeGenerator(new FixedTime());
$v2 = $tf->uuid2(0, 0x89abcdef);
var_dump($v2->getVersion() === 2);
var_dump(bin2hex(substr($v2->getBytes(), 0, 4)) === '89abcdef');
// time_mid from generator preserved
var_dump(bin2hex(substr($v2->getBytes(), 4, 2)) === '1111');

// --- name length cap (CR-009) ---
$threw = false;
try {
    // Allocate just over 16 MiB without hashing if possible — expect throw
    $big = str_repeat('x', 16 * 1024 * 1024 + 1);
    \FastUuid\Uuid::uuid5(\FastUuid\Uuid::fromString(Uuid::NAMESPACE_DNS), $big);
} catch (InvalidArgumentException) {
    $threw = true;
}
var_dump($threw);

// --- Nonstandard getVersion is null (CR-005) ---
// RFC variant + unassigned version nibble 0
$b = str_repeat("\x01", 16);
$b[6] = chr((ord($b[6]) & 0x0f) | 0x00);
$b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
$ns = Uuid::fromBytes($b);
var_dump($ns instanceof \FastUuid\Compat\Nonstandard\Uuid);
var_dump($ns->getVersion() === null && $ns->getFields()->getVersion() === null);
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
