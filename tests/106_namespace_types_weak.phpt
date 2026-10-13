--TEST--
Native name-based UUID factories preserve objects and coerce weak string namespace inputs
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Uuid as CompatUuid;
use FastUuid\Exception\InvalidArgumentException;
use FastUuid\Uuid;

$core = Uuid::fromString(Uuid::NAMESPACE_DNS);
$stringable = new class {
    public function __toString(): string { return Uuid::NAMESPACE_DNS; }
};
$coreOnly = new class($core) {
    public function __construct(private Uuid $core) {}
    public function getCore(): Uuid { return $this->core; }
};
$resource = fopen('php://memory', 'r');

foreach ([
    'uuid3' => '5df41881-3aed-3515-88a7-2f4a814cf09e',
    'uuid5' => '2ed6657d-e927-568b-95e1-2665a8aea6a2',
] as $method => $expected) {
    foreach ([Uuid::NAMESPACE_DNS, $core, CompatUuid::fromString(Uuid::NAMESPACE_DNS), $stringable, $coreOnly] as $namespace) {
        var_dump(Uuid::$method($namespace, 'www.example.com')->toString() === $expected);
    }

    foreach ([0, 1.5, true, false] as $namespace) {
        try {
            Uuid::$method($namespace, 'www.example.com');
            var_dump(false);
        } catch (InvalidArgumentException $e) {
            var_dump($e->getMessage() === 'Invalid namespace');
        }
    }

    foreach ([[], $resource] as $namespace) {
        try {
            Uuid::$method($namespace, 'www.example.com');
            var_dump(false);
        } catch (TypeError $e) {
            var_dump(str_contains($e->getMessage(), 'Argument #1 ($ns) must be of type object|string'));
        }
    }
}
fclose($resource);
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
