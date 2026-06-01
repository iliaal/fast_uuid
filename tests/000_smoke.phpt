--TEST--
fast_uuid: extension loads and reports its version
--EXTENSIONS--
fast_uuid
--FILE--
<?php
var_dump(extension_loaded('fast_uuid'));
var_dump((bool) preg_match('/^\d+\.\d+\.\d+/', phpversion('fast_uuid')));
?>
--EXPECT--
bool(true)
bool(true)
