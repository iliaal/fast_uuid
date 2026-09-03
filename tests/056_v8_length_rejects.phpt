--TEST--
uuid_v8 and uuid_v8_bin reject 15/17-byte input (CR-022)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
use FastUuid\Exception\InvalidArgumentException;

function v8Rejects(callable $fn): bool {
    try {
        $fn();
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
}

// Only exactly 16 bytes are accepted; off-by-one lengths throw on both forms.
foreach ([15, 17] as $len) {
    $b = str_repeat("\0", $len);
    var_dump(v8Rejects(fn() => uuid_v8($b)));
    var_dump(v8Rejects(fn() => uuid_v8_bin($b)));
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
