--TEST--
compat: GenericValidator, Rfc4122\Validator, NonstandardValidator and facade isValid() accept what ramsey/uuid 4.10 accepts
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Rfc4122\Validator as Rfc4122Validator;
use FastUuid\Compat\Uuid;
use FastUuid\Compat\Validator\GenericValidator;
use FastUuid\Compat\Validator\NonstandardValidator;

// ramsey/uuid 4.10's rules, as its validators implement them.
const WRAPPER = '/\A(?:urn:)?(?:uuid:)?(?:\{(.*)\}|(.*))\z/Dis';
function ramseyGeneric(string $s): bool
{
    if (strlen($s) > 47) {
        return false;
    }
    $s = preg_replace(WRAPPER, '$1$2', $s) ?? $s;

    return preg_match('/' . (new GenericValidator())->getPattern() . '/Dms', $s) === 1;
}
function ramseyRfc4122(string $s): bool
{
    if (strlen($s) > 47) {
        return false;
    }
    $s = strtolower(preg_replace(WRAPPER, '$1$2', $s) ?? $s);

    return $s === '00000000-0000-0000-0000-000000000000' || $s === 'ffffffff-ffff-ffff-ffff-ffffffffffff'
        || preg_match('/' . (new Rfc4122Validator())->getPattern() . '/Dms', $s) === 1;
}

$u = 'ff6f8cb0-c57d-11e1-9b21-0800200c9a66';
$inners = [$u, strtoupper($u), str_replace('-', '', $u), Uuid::NIL, 'ffffffff-ffff-ffff-ffff-ffffffffffff',
    'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF', substr($u, 1), $u . 'a', substr_replace($u, 'g', 35, 1), 'nope'];
foreach (str_split('0189abcfA') as $version) {
    foreach (str_split('0789bBcf') as $variant) {
        $x = $u;
        $x[14] = $version;
        $x[19] = $variant;
        $inners[] = $x;
    }
}
$prefixes = ['', 'urn:', 'uuid:', 'urn:uuid:', 'URN:UUID:', 'uuid:urn:', 'urn:urn:'];
$forms = [];
foreach ($inners as $in) {
    foreach ($prefixes as $a) {
        foreach ($prefixes as $b) {
            array_push($forms, $a . $b . $in, $a . '{' . $b . $in . '}', '{' . $a . $b . $in . '}', $a . '{{' . $in . '}}',
                $a . $in . '}', $a . '{' . $in, $a . $in . "\n");
        }
    }
}
$generic = new GenericValidator();
$rfc = new Rfc4122Validator();
$nonstandard = new NonstandardValidator();
// A subclass takes the stripWrappers()/isCanonicalShape() path instead of the native check.
$subclass = new class extends GenericValidator {
};
$ok = true;
foreach (array_unique($forms) as $s) {
    $want = ramseyGeneric($s);
    if ($generic->validate($s) !== $want || $nonstandard->validate($s) !== $want || $subclass->validate($s) !== $want
        || Uuid::isValid($s) !== $want
        || $rfc->validate($s) !== ramseyRfc4122($s)) {
        $ok = false;
        echo 'differs for ', var_export($s, true), "\n";
    }
}
var_dump($ok);
var_dump(count(array_unique($forms)) > 5000);

// A few fixed cases, so the expectations do not rest on the copied rules alone.
var_dump($generic->validate('{' . $u . '}'), $generic->validate('urn:uuid:{' . $u . '}'), $generic->validate('urn:' . $u));
var_dump(!$generic->validate('{urn:uuid:' . $u . '}'), !$generic->validate('{{' . $u . '}}'), !$generic->validate(str_replace('-', '', $u)));
var_dump($generic->validate('a1b2c3d4-e5f6-4718-f93a-4b5c6d7e8f90'), !$rfc->validate('a1b2c3d4-e5f6-4718-f93a-4b5c6d7e8f90'));
var_dump($rfc->validate('URN:UUID:{FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF}'), $rfc->validate(Uuid::NIL), $rfc->validate($u));
var_dump($generic->getPattern() === $nonstandard->getPattern());
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
