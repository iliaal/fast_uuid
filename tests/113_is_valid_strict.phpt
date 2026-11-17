--TEST--
uuid_is_valid() and Uuid::isValid() with $strict accept only ramsey/uuid's GenericValidator forms
--EXTENSIONS--
fast_uuid
--FILE--
<?php
$u = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$hex = str_replace('-', '', $u);
$accepted = [
    $u, strtoupper($u), '00000000-0000-0000-0000-000000000000', 'ffffffff-ffff-ffff-ffff-ffffffffffff',
    '{' . $u . '}', 'urn:' . $u, 'uuid:' . $u, 'urn:uuid:' . $u, 'URN:UUID:' . $u, 'Urn:Uuid:' . $u,
    'urn:{' . $u . '}', 'uuid:{' . $u . '}', 'urn:uuid:{' . $u . '}',
];
$rejected = [
    '', $hex, '{' . $hex . '}', 'urn:' . $hex, 'urn:uuid:' . $hex, '{{' . $u . '}}', '{urn:uuid:' . $u . '}',
    '{urn:' . $u . '}', '{uuid:' . $u . '}', 'urn:uuid:{{' . $u . '}}', 'urn:{{' . $u . '}}', 'uuid:urn:' . $u,
    'urn:urn:' . $u, 'uuid:uuid:' . $u, 'urn:urn:' . $hex, 'urn:uuid:urn:uuid:' . $u, '{' . $u, $u . '}',
    'urn:' . $u . '}', ' ' . $u, $u . ' ', $u . "\n", substr($u, 1), $u . 'a', substr_replace($u, 'g', 35, 1),
    substr_replace($u, '-', 0, 1), substr_replace($u, 'a', 8, 1), 'urn: ' . $u, 'uid:' . $u,
];
$ok = true;
foreach ([[$accepted, true], [$rejected, false]] as [$forms, $want]) {
    foreach ($forms as $s) {
        $dynamic = 'uuid_is_valid';
        foreach ([uuid_is_valid($s, true), $dynamic($s, true), FastUuid\Uuid::isValid($s, true)] as $got) {
            if ($got !== $want) {
                $ok = false;
                echo 'wrong for ', var_export($s, true), "\n";
            }
        }
    }
}
var_dump($ok);

// Without $strict the tolerant set is unchanged.
var_dump(uuid_is_valid($hex), uuid_is_valid('{urn:uuid:' . $u . '}'), uuid_is_valid($hex, false), FastUuid\Uuid::isValid($hex));

// Weak-mode coercion of both arguments, through the frameless and the dynamic call.
$dynamic = 'uuid_is_valid';
var_dump(uuid_is_valid($u, 1), uuid_is_valid($hex, 1), $dynamic($hex, 0), uuid_is_valid(new class { public function __toString(): string { return '6ba7b810-9dad-11d1-80b4-00c04fd430c8'; } }, 1));
try {
    uuid_is_valid($u, []);
    var_dump(false);
} catch (TypeError $e) {
    var_dump($e->getMessage() === 'uuid_is_valid(): Argument #2 ($strict) must be of type bool, array given');
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
