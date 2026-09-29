--TEST--
compat: Hexadecimal and Integer accept exactly their digit grammar; validator keeps the wrapper grammar at 36 bytes
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer;
use FastUuid\Compat\Validator\GenericValidator;
use FastUuid\Compat\Validator\NonstandardValidator;
use FastUuid\Exception\InvalidArgumentException;

function rejects(callable $make): bool
{
    try {
        $make();
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
}

// The character-class scan must agree with the anchored /D expressions it replaced.
$hexCases = ['', '0x', '0X', '0xg', 'g', 'ab ', ' ab', "ab\n", "\nab", "ab\0", "\0", 'a-b', '0x0x1', 'ａｂ', "\xff", 'ab cd'];
$hexPcre = static fn(string $v): bool => preg_match('/^[0-9a-fA-F]+$/D', strlen($v) >= 2 && $v[0] === '0' && ($v[1] === 'x' || $v[1] === 'X') ? substr($v, 2) : $v) === 1;
$hexAgree = true;
foreach (array_merge($hexCases, ['0', 'a', 'A', 'f', '0xa', '0XA', 'deadBEEF', str_repeat('f', 4096), '0123456789abcdefABCDEF']) as $v) {
    $hexAgree = $hexAgree && (!rejects(static fn() => new Hexadecimal($v)) === $hexPcre($v));
}
var_dump($hexAgree);
var_dump(rejects(static fn() => new Hexadecimal('')));
var_dump(rejects(static fn() => new Hexadecimal('0x')));
var_dump(rejects(static fn() => new Hexadecimal("ab\n")));
var_dump(rejects(static fn() => new Hexadecimal('abg')));
var_dump((string) new Hexadecimal('0xDeadBeef') === 'deadbeef');
var_dump((string) new Hexadecimal('0') === '0');

$intCases = ['', '+', '-', '+-1', '--1', '1 ', ' 1', "1\n", "\n1", "1\0", '1.5', '1e3', '0x1', '１２', "\xff", '١٢'];
$intPcre = static function (string $v): bool {
    if ($v !== '' && ($v[0] === '+' || $v[0] === '-')) {
        $v = substr($v, 1);
    }
    return preg_match('/^[0-9]+$/D', $v) === 1;
};
$intAgree = true;
foreach (array_merge($intCases, ['0', '-0', '+0', '007', '-007', '+7', '9', str_repeat('9', 4096), '340282366920938463463374607431768211455']) as $v) {
    $intAgree = $intAgree && (!rejects(static fn() => new Integer($v)) === $intPcre($v));
}
var_dump($intAgree);
var_dump(rejects(static fn() => new Integer('')));
var_dump(rejects(static fn() => new Integer('-')));
var_dump(rejects(static fn() => new Integer("12\n")));
var_dump(rejects(static fn() => new Integer(1.5)));
var_dump((string) new Integer('-007') === '-7');
var_dump((string) new Integer(42) === '42');
var_dump((string) new Integer(1.0E+3) === '1000');

// 36-byte validator inputs: canonical passes, every wrapped or padded form of
// that length is still judged after unwrapping.
$canonical = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$generic = new GenericValidator();
$lax = new NonstandardValidator();
var_dump(strlen($canonical) === 36);
var_dump($generic->validate($canonical));
var_dump($generic->validate(strtoupper($canonical)));
var_dump($lax->validate($canonical));
var_dump(!$generic->validate(substr($canonical, 0, 35) . "\n"));
var_dump(!$generic->validate(substr($canonical, 0, 35) . 'g'));
var_dump(!$generic->validate(str_replace('-', '_', $canonical)));
var_dump(!$generic->validate('{' . substr($canonical, 1, 34) . '}'));
var_dump(!$generic->validate('urn:uuid:' . substr($canonical, 0, 27)));
var_dump(!$generic->validate('URN:UUID:' . substr($canonical, 0, 27)));
var_dump(!$lax->validate('{' . substr($canonical, 1, 34) . '}'));
var_dump($generic->validate('{' . $canonical . '}'));
var_dump($generic->validate('urn:uuid:' . $canonical));
var_dump($generic->validate('URN:UUID:{' . $canonical . '}'));
var_dump($generic->validate('00000000-0000-0000-0000-000000000000'));
var_dump(!$generic->validate('ffffffff-ffff-ffff-ffff-ffffffffffff'));
var_dump($lax->validate('ffffffff-ffff-ffff-ffff-ffffffffffff'));
var_dump(!$generic->validate(''));
var_dump(!$generic->validate(str_repeat('a', 48)));
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
