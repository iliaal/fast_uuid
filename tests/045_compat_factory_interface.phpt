--TEST--
Compat static facade exposes a replaceable factory interface
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Uuid;
use FastUuid\Compat\UuidFactory;
use FastUuid\Compat\UuidFactoryInterface;
use FastUuid\Compat\UuidInterface;

var_dump(interface_exists(UuidFactoryInterface::class));
var_dump(is_subclass_of(UuidFactory::class, UuidFactoryInterface::class));

$parameter = (new ReflectionMethod(Uuid::class, 'setFactory'))->getParameters()[0];
var_dump((string) $parameter->getType() === UuidFactoryInterface::class);

class DecoratingFactory extends UuidFactory
{
    public int $uuid4Calls = 0;

    public function uuid4(): UuidInterface
    {
        $this->uuid4Calls++;
        return parent::uuid4();
    }
}

$decorator = new DecoratingFactory();
var_dump($decorator instanceof UuidFactoryInterface);
Uuid::setFactory($decorator);
$uuid = Uuid::uuid4();
var_dump($decorator->uuid4Calls === 1 && $uuid->getVersion() === 4);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
