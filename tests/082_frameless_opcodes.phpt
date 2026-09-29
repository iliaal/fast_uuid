--TEST--
Direct procedural calls compile to frameless opcodes on PHP 8.4+
--EXTENSIONS--
fast_uuid
opcache
--SKIPIF--
<?php if (PHP_VERSION_ID < 80400) die('skip frameless calls need PHP 8.4+'); ?>
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
opcache.opt_debug_level=0x10000
--FILE--
<?php
namespace Fl {
function f(string $s): array {
    return [uuid_v4(), uuid_to_bin($s), uuid_v5($s, 'x'), uuid_v7_batch(1)];
}
}
namespace {
$s = uuid_v7();
$b = uuid_v8_bin(uuid_to_bin($s));
$n = uuid_v4(1);
}
?>
--EXPECTF--
$_main:
%A
%w%d %r[TV]%r%d = FRAMELESS_ICALL_0(uuid_v7)
%A
%w%d %r[TV]%r%d = FRAMELESS_ICALL_1(uuid_to_bin) CV0($s)
%w%d %r[TV]%r%d = FRAMELESS_ICALL_1(uuid_v8_bin) T%d
%A
%w%d INIT_FCALL 1 %d string("uuid_v4")
%A
Fl\f:
%A
%w%d JMP_FRAMELESS %d string("fl\\uuid_v4") %d
%A
%w%d %r[TV]%r%d = FRAMELESS_ICALL_0(uuid_v4)
%A
%w%d %r[TV]%r%d = FRAMELESS_ICALL_1(uuid_to_bin) CV0($s)
%A
%w%d %r[TV]%r%d = FRAMELESS_ICALL_2(uuid_v5) CV0($s) string("x")
%A
%w%d %r[TV]%r%d = FRAMELESS_ICALL_1(uuid_v7_batch) int(1)
%A
