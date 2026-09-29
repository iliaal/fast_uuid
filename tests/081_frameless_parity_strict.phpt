--TEST--
Frameless procedural calls behave like the ZPP path (strict_types=1, global and namespaced code)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
declare(strict_types=1);

namespace {
require __DIR__ . '/_frameless.inc';

fl_run('global', [
        'uuid_v1'                => fn() => uuid_v1(),
        'uuid_v1_bin'            => fn() => uuid_v1_bin(),
        'uuid_v4'                => fn() => uuid_v4(),
        'uuid_v4_bin'            => fn() => uuid_v4_bin(),
        'uuid_v4_fast'           => fn() => uuid_v4_fast(),
        'uuid_v4_fast_bin'       => fn() => uuid_v4_fast_bin(),
        'uuid_v6'                => fn() => uuid_v6(),
        'uuid_v6_bin'            => fn() => uuid_v6_bin(),
        'uuid_v7'                => fn() => uuid_v7(),
        'uuid_v7_bin'            => fn() => uuid_v7_bin(),
        'uuid_v7_at'             => fn($a) => uuid_v7_at($a),
        'uuid_v7_at_bin'         => fn($a) => uuid_v7_at_bin($a),
        'uuid_v3'                => fn($a, $b) => uuid_v3($a, $b),
        'uuid_v3_bin'            => fn($a, $b) => uuid_v3_bin($a, $b),
        'uuid_v5'                => fn($a, $b) => uuid_v5($a, $b),
        'uuid_v5_bin'            => fn($a, $b) => uuid_v5_bin($a, $b),
        'uuid_v8'                => fn($a) => uuid_v8($a),
        'uuid_v8_bin'            => fn($a) => uuid_v8_bin($a),
        'uuid_v4_batch'          => fn($a) => uuid_v4_batch($a),
        'uuid_v4_bin_batch'      => fn($a) => uuid_v4_bin_batch($a),
        'uuid_v7_batch'          => fn($a) => uuid_v7_batch($a),
        'uuid_v7_bin_batch'      => fn($a) => uuid_v7_bin_batch($a),
        'uuid_to_bin'            => fn($a) => uuid_to_bin($a),
        'uuid_from_bin'          => fn($a) => uuid_from_bin($a),
        'uuid_is_valid'          => fn($a) => uuid_is_valid($a),
        'fast_uuid_random_bytes' => fn($a) => fast_uuid_random_bytes($a),
], fn(string $f, array $a) => $f(...$a));

fl_show('uuid_v4(1)', fn() => uuid_v4(1));
fl_show('uuid_to_bin()', fn() => uuid_to_bin());

fl_show('uuid_to_bin(int)', fn() => uuid_to_bin(123));
fl_show('uuid_to_bin(null)', fn() => uuid_to_bin(null));
fl_show('uuid_to_bin(Stringable)', fn() => uuid_to_bin(new FlStringable('6ba7b810-9dad-11d1-80b4-00c04fd430c8')));
fl_show('uuid_v3(ns, int)', fn() => uuid_v3(FastUuid\Uuid::NAMESPACE_DNS, 1));
fl_show('uuid_v3(DNS, www.example.com)', fn() => uuid_v3(FastUuid\Uuid::NAMESPACE_DNS, 'www.example.com'));
fl_show('uuid_v8_bin(16 bytes)', fn() => uuid_v8_bin(str_repeat("\xff", 16)));
fl_show('uuid_v7_at(3.0)', fn() => uuid_v7_at(3.0));
fl_show('uuid_v7_at("3")', fn() => uuid_v7_at('3'));
fl_show('uuid_v7_at(PHP_INT_MAX)', fn() => uuid_v7_at(PHP_INT_MAX));
fl_show('uuid_v4_bin_batch([])', fn() => uuid_v4_bin_batch([]));
fl_show('uuid_v7_batch(3)', fn() => uuid_v7_batch(3), false);
fl_show('fast_uuid_random_bytes(0)', fn() => fast_uuid_random_bytes(0));
fl_show('uuid_from_bin(15 bytes)', fn() => uuid_from_bin(str_repeat('a', 15)));

// Strict mode rejects before coercing, so the referenced variable is untouched.
$n = 123;
$r = &$n;
try {
    uuid_is_valid($r);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
var_dump($n);

// A discarded result still throws, and the trace names the function.
try {
    uuid_v8('short');
} catch (FastUuid\Exception\InvalidArgumentException $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', $e->getTrace()[0]['function'], "\n";
}
}

namespace Fl {
\fl_run('namespaced', [
        'uuid_v1'                => fn() => uuid_v1(),
        'uuid_v1_bin'            => fn() => uuid_v1_bin(),
        'uuid_v4'                => fn() => uuid_v4(),
        'uuid_v4_bin'            => fn() => uuid_v4_bin(),
        'uuid_v4_fast'           => fn() => uuid_v4_fast(),
        'uuid_v4_fast_bin'       => fn() => uuid_v4_fast_bin(),
        'uuid_v6'                => fn() => uuid_v6(),
        'uuid_v6_bin'            => fn() => uuid_v6_bin(),
        'uuid_v7'                => fn() => uuid_v7(),
        'uuid_v7_bin'            => fn() => uuid_v7_bin(),
        'uuid_v7_at'             => fn($a) => uuid_v7_at($a),
        'uuid_v7_at_bin'         => fn($a) => uuid_v7_at_bin($a),
        'uuid_v3'                => fn($a, $b) => uuid_v3($a, $b),
        'uuid_v3_bin'            => fn($a, $b) => uuid_v3_bin($a, $b),
        'uuid_v5'                => fn($a, $b) => uuid_v5($a, $b),
        'uuid_v5_bin'            => fn($a, $b) => uuid_v5_bin($a, $b),
        'uuid_v8'                => fn($a) => uuid_v8($a),
        'uuid_v8_bin'            => fn($a) => uuid_v8_bin($a),
        'uuid_v4_batch'          => fn($a) => uuid_v4_batch($a),
        'uuid_v4_bin_batch'      => fn($a) => uuid_v4_bin_batch($a),
        'uuid_v7_batch'          => fn($a) => uuid_v7_batch($a),
        'uuid_v7_bin_batch'      => fn($a) => uuid_v7_bin_batch($a),
        'uuid_to_bin'            => fn($a) => uuid_to_bin($a),
        'uuid_from_bin'          => fn($a) => uuid_from_bin($a),
        'uuid_is_valid'          => fn($a) => uuid_is_valid($a),
        'fast_uuid_random_bytes' => fn($a) => fast_uuid_random_bytes($a),
], fn(string $f, array $a) => $f(...$a));

\fl_show('Fl: uuid_to_bin(int)', fn() => uuid_to_bin(123));
\fl_show('Fl: uuid_v7_at_bin(null)', fn() => uuid_v7_at_bin(null));
\fl_show('Fl: uuid_v5_bin(null, x)', fn() => uuid_v5_bin(null, 'x'));
var_dump(uuid_is_valid(uuid_v7()));
}
?>
--EXPECT--
global: 230 calls, 168 distinct outcomes, 0 mismatches
uuid_v4(1) => ArgumentCountError: uuid_v4() expects exactly 0 arguments, 1 given
uuid_to_bin() => ArgumentCountError: uuid_to_bin() expects exactly 1 argument, 0 given
uuid_to_bin(int) => TypeError: uuid_to_bin(): Argument #1 ($uuid) must be of type string, int given
uuid_to_bin(null) => TypeError: uuid_to_bin(): Argument #1 ($uuid) must be of type string, null given
uuid_to_bin(Stringable) => TypeError: uuid_to_bin(): Argument #1 ($uuid) must be of type string, FlStringable given
uuid_v3(ns, int) => TypeError: uuid_v3(): Argument #2 ($name) must be of type string, int given
uuid_v3(DNS, www.example.com) => string "5df41881-3aed-3515-88a7-2f4a814cf09e"
uuid_v8_bin(16 bytes) => string 0xffffffffffff8fffbfffffffffffffff
uuid_v7_at(3.0) => TypeError: uuid_v7_at(): Argument #1 ($unixMillis) must be of type int, float given
uuid_v7_at("3") => TypeError: uuid_v7_at(): Argument #1 ($unixMillis) must be of type int, string given
uuid_v7_at(PHP_INT_MAX) => FastUuid\Exception\InvalidArgumentException: v7 millisecond timestamp out of range (0 .. 281474976710655)
uuid_v4_bin_batch([]) => TypeError: uuid_v4_bin_batch(): Argument #1 ($count) must be of type int, array given
uuid_v7_batch(3) => array(3) of string(36)
fast_uuid_random_bytes(0) => FastUuid\Exception\InvalidArgumentException: length must be > 0
uuid_from_bin(15 bytes) => FastUuid\Exception\InvalidArgumentException: Expected 16 bytes
uuid_is_valid(): Argument #1 ($uuid) must be of type string, int given
int(123)
FastUuid\Exception\InvalidArgumentException: uuid8 requires 16 bytes in uuid_v8
namespaced: 230 calls, 168 distinct outcomes, 0 mismatches
Fl: uuid_to_bin(int) => TypeError: uuid_to_bin(): Argument #1 ($uuid) must be of type string, int given
Fl: uuid_v7_at_bin(null) => TypeError: uuid_v7_at_bin(): Argument #1 ($unixMillis) must be of type int, null given
Fl: uuid_v5_bin(null, x) => TypeError: uuid_v5_bin(): Argument #1 ($ns) must be of type string, null given
bool(true)
