--TEST--
fast_uuid: extension loads and reports its version
--EXTENSIONS--
fast_uuid
--FILE--
<?php
var_dump(extension_loaded('fast_uuid'));
var_dump(phpversion('fast_uuid'));
?>
--EXPECT--
bool(true)
string(5) "0.1.0"
