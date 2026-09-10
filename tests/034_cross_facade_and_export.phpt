--TEST--
Cross-facade equals/compareTo; var_dump debug info and var_export/__set_state round-trip
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Uuid;
use FastUuid\Compat\Uuid as CompatUuid;
use FastUuid\Exception\InvalidArgumentException;

$s = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$core   = Uuid::fromString($s);
$compat = CompatUuid::fromString($s);

var_dump($core->equals($compat));
var_dump($compat->equals($core));
var_dump($core->compareTo($compat) === 0);
var_dump($compat->equals($compat));
var_dump(!$core->equals(CompatUuid::uuid4()));

$str = new class { public function __toString(): string { return '6ba7b810-9dad-11d1-80b4-00c04fd430c8'; } };
var_dump($core->equals($str));
$junk = new class { public function __toString(): string { return 'not-a-uuid'; } };
var_dump($core->equals($junk) === false);
$threw = false;
try { $core->compareTo($junk); } catch (InvalidArgumentException) { $threw = true; }
var_dump($threw);
$boom = new class { public function __toString(): string { throw new \RuntimeException('boom'); } };
$threw = false;
try { $core->equals($boom); } catch (\RuntimeException) { $threw = true; }
var_dump($threw);

ob_start(); var_dump($core); $dump = ob_get_clean();
var_dump(str_contains($dump, '["uuid"]=>') && str_contains($dump, $s));

$code = var_export($core, true);
var_dump(str_contains($code, '__set_state') && str_contains($code, $s));
$rebuilt = eval('return ' . $code . ';');
var_dump($rebuilt instanceof Uuid && $rebuilt->equals($core));

foreach ([[], ['uuid' => 'nonsense'], ['uuid' => 42]] as $bad) {
    $threw = false;
    try { Uuid::__set_state($bad); } catch (InvalidArgumentException) { $threw = true; }
    var_dump($threw);
}

var_dump(json_encode($core) === '"' . $s . '"');
var_dump((array)$core === []);
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
