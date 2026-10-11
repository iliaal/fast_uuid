--TEST--
Compat UUID restoration preserves identity and presentation when factory codec lookup throws
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Codec\CodecInterface;
use FastUuid\Compat\Codec\StringCodec;
use FastUuid\Compat\Codec\TimestampFirstCombCodec;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;

$originalFactory = Uuid::getFactory();
$factory = new class extends UuidFactory {
    public bool $fail = true;
    public function getCodec(): CodecInterface
    {
        if ($this->fail) {
            throw new RuntimeException('codec unavailable');
        }
        return parent::getCodec();
    }
};
Uuid::setFactory($factory);

foreach ([new StringCodec(), new TimestampFirstCombCodec()] as $oldCodec) {
    foreach (range(1, 9) as $version) {
        // Version 9 exercises the nonstandard wrapper as well as RFC versions 1-8.
        $old = \FastUuid\Uuid::fromString("00112233-4455-{$version}677-8899-aabbccddeeff");
        $new = \FastUuid\Uuid::fromString("ffeeddcc-bbaa-{$version}988-8776-554433221100");
        foreach ([$new->getBytes(), $new->toString()] as $payload) {
            foreach (['unserialize', '__unserialize'] as $method) {
                $uuid = $originalFactory->wrap($old, $oldCodec);
                $before = [$uuid->getCore(), $uuid->toString(), $uuid->getBytes()];
                try {
                    $uuid->$method($method === '__unserialize' ? ['bytes' => $payload] : $payload);
                    throw new LogicException('restore unexpectedly succeeded');
                } catch (RuntimeException $e) {
                    if ($e->getMessage() !== 'codec unavailable') { throw $e; }
                }
                if ([$uuid->getCore(), $uuid->toString(), $uuid->getBytes()] !== $before) {
                    throw new LogicException('failed restore changed identity or presentation');
                }
            }
        }
    }
}
echo "failed restores preserve state\n";

$factory->fail = false;
foreach ([new StringCodec(), new TimestampFirstCombCodec()] as $codec) {
    $factory->setCodec($codec);
    foreach ([$new->getBytes(), $new->toString()] as $payload) {
        foreach (['unserialize', '__unserialize'] as $method) {
            $uuid = $originalFactory->wrap($old, $oldCodec);
            $uuid->$method($method === '__unserialize' ? ['bytes' => $payload] : $payload);
            var_dump($uuid->getCore()->equals($new)
                && $uuid->toString() === $codec->encode($uuid)
                && $uuid->getBytes() === $codec->encodeBinary($uuid));
        }
    }
}
Uuid::setFactory($originalFactory);
?>
--EXPECT--
failed restores preserve state
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
