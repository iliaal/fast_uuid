--TEST--
compat: nonstandard validator preserves canonical and wrapped shape validation
--EXTENSIONS--
fast_uuid
--FILE--
<?php
require __DIR__ . '/_autoload.inc';

use FastUuid\Compat\Validator\NonstandardValidator;

$validator = new NonstandardValidator();
$canonical = '00112233-4455-0677-0899-aabbccddeeff';
$valid = [
    $canonical,
    strtoupper($canonical),
    '00000000-0000-0000-0000-000000000000',
    'ffffffff-ffff-ffff-ffff-ffffffffffff',
];
foreach ($valid as $uuid) {
    foreach ([$uuid, '{' . $uuid . '}', 'URN:UUID:' . $uuid,
        '{urn:uuid:' . $uuid . '}', 'urn:uuid:{' . $uuid . '}',
        '{{' . $uuid . '}}'] as $input) {
        if (!$validator->validate($input)) {
            throw new RuntimeException('Rejected valid input: ' . $input);
        }
    }
}
var_dump(true);

// Every byte position is significant, including embedded control/high bytes.
foreach (range(0, 35) as $offset) {
    foreach (["\0", "\n", "\xff", 'g', '/'] as $replacement) {
        $input = $canonical;
        $input[$offset] = $replacement;
        if ($validator->validate($input)) {
            throw new RuntimeException('Accepted invalid byte at ' . $offset);
        }
    }
}
var_dump(true);

// These wrapped inputs are exactly 36 bytes but unwrap to a shorter value.
$invalid = [
    '{' . substr($canonical, 0, 34) . '}',
    'urn:uuid:' . substr($canonical, 0, 27),
    '{urn:uuid:' . substr($canonical, 0, 25) . '}',
    'urn:uuid:{' . substr($canonical, 0, 25) . '}',
    str_replace('-', '', $canonical),
    "\n" . $canonical,
    $canonical . "\n",
    '{{{' . $canonical . '}}}',
    'urn:uuid:urn:uuid:' . $canonical,
    '',
];
foreach ($invalid as $input) {
    if ($validator->validate($input)) {
        throw new RuntimeException('Accepted invalid input: ' . $input);
    }
}
var_dump(true);

// All version and variant nibbles remain valid for this shape-only validator.
foreach (str_split('0123456789abcdefABCDEF') as $version) {
    foreach (str_split('0123456789abcdefABCDEF') as $variant) {
        $input = $canonical;
        $input[14] = $version;
        $input[19] = $variant;
        if (!$validator->validate($input)) {
            throw new RuntimeException('Rejected nonstandard nibbles');
        }
    }
}
var_dump(true);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
