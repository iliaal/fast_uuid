--TEST--
UUID resolution calls getCore()/getBytes() once, propagates their exceptions, and never autoloads type names
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Uuid;
use FastUuid\UuidInterface;
use FastUuid\Compat\Uuid as CompatUuid;

$dns = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$other = '00112233-4455-4677-8899-aabbccddeeff';
$name = 'www.example.com';
$expected3 = '5df41881-3aed-3515-88a7-2f4a814cf09e';
$expected5 = '2ed6657d-e927-568b-95e1-2665a8aea6a2';
$core = Uuid::fromString($dns);
$dnsBytes = (string) hex2bin(str_replace('-', '', $dns));

final class Calls
{
    public static array $n = [];
    public static function hit(string $what): void { self::$n[$what] = (self::$n[$what] ?? 0) + 1; }
    public static function reset(): void { self::$n = []; }
    public static function count(string $what): int { return self::$n[$what] ?? 0; }
}

// Compat wrapper as the argument of equals(), compareTo() and the name-based factories.
$compat = CompatUuid::fromString($dns);
var_dump($core->equals($compat));
var_dump($core->compareTo($compat) === 0);
var_dump(!$core->equals(CompatUuid::uuid4()));
var_dump(Uuid::uuid5($compat, $name)->toString() === $expected5);
var_dump(Uuid::uuid3($compat, $name)->toString() === $expected3);
var_dump(CompatUuid::uuid5($compat, $name)->toString() === $expected5);

// A userland getCore() wins over a different __toString() and runs exactly once.
final class CoreWins implements UuidInterface
{
    public function __construct(private Uuid $core, private string $text) {}
    public function __toString(): string { Calls::hit('toString'); return $this->text; }
    public function jsonSerialize(): mixed { return $this->text; }
    public function getCore(): Uuid { Calls::hit('getCore'); return $this->core; }
    public function getBytes(): string { Calls::hit('getBytes'); return str_repeat("\x00", 16); }
}
$coreWins = new CoreWins($core, $other);
Calls::reset();
var_dump($core->equals($coreWins));
var_dump(Calls::count('getCore') === 1 && Calls::count('getBytes') === 0 && Calls::count('toString') === 0);
var_dump($core->compareTo($coreWins) === 0);
var_dump(Uuid::uuid5($coreWins, $name)->toString() === $expected5);
var_dump(Calls::count('getCore') === 3);
var_dump(!Uuid::fromString($other)->equals($coreWins));

// getCore() type spellings that are not the exact class name: alias and case-insensitive supertype.
// class_alias() accepts internal classes only from PHP 8.3; earlier the alias is unresolvable and getCore() is skipped.
$aliasable = PHP_VERSION_ID >= 80300;
if ($aliasable) {
    class_alias(Uuid::class, 'AliasedCoreType');
}
final class AliasCore
{
    public function __toString(): string { return '00000000-0000-4000-8000-000000000000'; }
    public function getCore(): AliasedCoreType { return Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8'); }
}
final class SupertypeCore
{
    public function __toString(): string { return '00000000-0000-4000-8000-000000000000'; }
    public function getCore(): fastuuid\UUIDINTERFACE { return Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8'); }
}
var_dump($core->equals(new AliasCore()) === $aliasable);
var_dump($core->equals(new SupertypeCore()));

// A throwing getCore() propagates its own exception and stops the fallback chain.
final class ThrowingCore implements UuidInterface
{
    public function __toString(): string { Calls::hit('toString'); return '6ba7b810-9dad-11d1-80b4-00c04fd430c8'; }
    public function jsonSerialize(): mixed { return (string) $this; }
    public function getCore(): Uuid { Calls::hit('getCore'); throw new RuntimeException('core boom'); }
    public function getBytes(): string { Calls::hit('getBytes'); return (string) hex2bin('6ba7b8109dad11d180b400c04fd430c8'); }
}
foreach (['equals', 'compareTo', 'uuid3', 'uuid5'] as $op) {
    Calls::reset();
    $msg = null;
    try {
        match ($op) {
            'equals' => $core->equals(new ThrowingCore()),
            'compareTo' => $core->compareTo(new ThrowingCore()),
            'uuid3' => Uuid::uuid3(new ThrowingCore(), $name),
            'uuid5' => Uuid::uuid5(new ThrowingCore(), $name),
        };
    } catch (RuntimeException $e) {
        $msg = $e->getMessage();
    }
    var_dump($msg === 'core boom' && Calls::count('getCore') === 1
        && Calls::count('getBytes') === 0 && Calls::count('toString') === 0);
}

// getCore() returning something that is not a native Uuid falls through to getBytes().
final class BadCoreThenBytes
{
    public function __toString(): string { return '00000000-0000-4000-8000-000000000000'; }
    public function getCore() { Calls::hit('getCore'); return 42; }
    public function getBytes(): string { Calls::hit('getBytes'); return (string) hex2bin('6ba7b8109dad11d180b400c04fd430c8'); }
}
Calls::reset();
var_dump($core->equals(new BadCoreThenBytes()));
var_dump(Calls::count('getCore') === 1 && Calls::count('getBytes') === 1);

// getBytes()-only Stringable: the raw bytes win over a different __toString().
final class BytesOnly
{
    public function __construct(private string $bytes, private string $text) {}
    public function __toString(): string { Calls::hit('toString'); return $this->text; }
    public function getBytes(): string { Calls::hit('getBytes'); return $this->bytes; }
}
Calls::reset();
var_dump($core->equals(new BytesOnly($dnsBytes, $other)));
var_dump(Calls::count('getBytes') === 1 && Calls::count('toString') === 0);
var_dump(!Uuid::fromString($other)->equals(new BytesOnly($dnsBytes, $other)));
var_dump(Uuid::uuid5(new BytesOnly($dnsBytes, $other), $name)->toString() === $expected5);

// A getBytes() that is not 16 bytes falls back to parsing the Stringable form.
var_dump($core->equals(new BytesOnly('short', $dns)));
var_dump(!$core->equals(new BytesOnly('short', $other)));

// A throwing getBytes() propagates and never reaches __toString().
final class ThrowingBytes
{
    public function __toString(): string { Calls::hit('toString'); return '6ba7b810-9dad-11d1-80b4-00c04fd430c8'; }
    public function getBytes(): string { Calls::hit('getBytes'); throw new LogicException('bytes boom'); }
}
Calls::reset();
$msg = null;
try { $core->equals(new ThrowingBytes()); } catch (LogicException $e) { $msg = $e->getMessage(); }
var_dump($msg === 'bytes boom' && Calls::count('getBytes') === 1 && Calls::count('toString') === 0);

// Non-public and static accessors are never invoked; the Stringable form decides.
final class HiddenAccessors
{
    public function __toString(): string { return '6ba7b810-9dad-11d1-80b4-00c04fd430c8'; }
    private function getCore(): Uuid { throw new LogicException('private getCore must not run'); }
    public static function getBytes(): string { throw new LogicException('static getBytes must not run'); }
}
var_dump($core->equals(new HiddenAccessors()));

// A getCore() return type naming an unloaded class is not autoloaded and the method is skipped.
final class MissingTypeCore
{
    public function __toString(): string { return '6ba7b810-9dad-11d1-80b4-00c04fd430c8'; }
    public function getCore(): Not\Loaded\CoreType { throw new LogicException('unloaded return type must not run'); }
}
$autoloaded = [];
spl_autoload_register(static function (string $class) use (&$autoloaded): void { $autoloaded[] = $class; });
var_dump($core->equals(new MissingTypeCore()));
var_dump(!in_array('Not\Loaded\CoreType', $autoloaded, true));
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
