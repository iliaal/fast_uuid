--TEST--
compat: default node/time providers; custom TimeGenerator nibble fixup and uuid6 routing
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Provider\DefaultTimeGenerator;
use FastUuid\Compat\Provider\RandomNodeProvider;
use FastUuid\Compat\Provider\TimeGeneratorInterface;
use FastUuid\Compat\Rfc4122\UuidV1;
use FastUuid\Compat\Rfc4122\UuidV6;
use FastUuid\Compat\UuidFactory;
use FastUuid\Exception\InvalidArgumentException;

// RandomNodeProvider: 6 bytes with the RFC 9562 multicast bit set.
$node = (new RandomNodeProvider())->getNode();
var_dump(strlen($node) === 6);
var_dump((ord($node[0]) & 0x01) === 1);

// DefaultTimeGenerator: valid v1 bytes.
$b = (new DefaultTimeGenerator())->generate();
var_dump(strlen($b) === 16);
var_dump((ord($b[6]) >> 4) === 1);
var_dump((ord($b[8]) & 0xc0) === 0x80);

// A ramsey-style generator (leaves version/variant nibbles to the factory)
// produces valid v1 and v6 UUIDs; fixed input makes the output deterministic.
$f = new UuidFactory();
$f->setTimeGenerator(new class implements TimeGeneratorInterface {
    public function generate(int|string|null $node = null, ?int $clockSeq = null): string
    {
        return "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f";
    }
});
$u1 = $f->uuid1();
var_dump($u1 instanceof UuidV1);
var_dump($u1->toString() === '00010203-0405-1607-8809-0a0b0c0d0e0f');

// uuid6 routes through the same generator, reordering the timestamp
// most-significant-first: both versions decode to the identical timestamp.
$u6 = $f->uuid6();
var_dump($u6 instanceof UuidV6);
var_dump($u6->toString() === '60704050-0010-6203-8809-0a0b0c0d0e0f');
var_dump($u6->getFields()->getTimestamp()->toString() === $u1->getFields()->getTimestamp()->toString());

// Wrong-length generator output is rejected, not indexed out of range.
$bad = new UuidFactory();
$bad->setTimeGenerator(new class implements TimeGeneratorInterface {
    public function generate(int|string|null $node = null, ?int $clockSeq = null): string
    {
        return 'short';
    }
});
foreach (['uuid1', 'uuid6'] as $m) {
    $threw = false;
    try { $bad->$m(); } catch (InvalidArgumentException) { $threw = true; }
    var_dump($threw);
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
