--TEST--
Frameless procedural calls behave like the ZPP path (coercive typing, global and namespaced code)
--EXTENSIONS--
fast_uuid
--FILE--
<?php
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

// A call whose argument count differs from the arity is never frameless.
fl_show('uuid_v4(1)', fn() => uuid_v4(1));
fl_show('uuid_to_bin()', fn() => uuid_to_bin());

fl_show('uuid_to_bin(int)', fn() => uuid_to_bin(123));
fl_show('uuid_to_bin(null)', fn() => uuid_to_bin(null));
fl_show('uuid_to_bin(Stringable)', fn() => uuid_to_bin(new FlStringable('6BA7B810-9DAD-11D1-80B4-00C04FD430C8')));
fl_show('uuid_is_valid(throwing Stringable)', fn() => uuid_is_valid(new FlThrowingStringable()));
fl_show('uuid_v5(DNS, www.example.com)', fn() => uuid_v5(FastUuid\Uuid::NAMESPACE_DNS, 'www.example.com'));
fl_show('uuid_v3(bad ns)', fn() => uuid_v3('nope', 'x'));
fl_show('uuid_v8(15 bytes)', fn() => uuid_v8(str_repeat('a', 15)));
fl_show('uuid_v7_at(3.5)', fn() => uuid_v7_at(3.5), false);
fl_show('uuid_v7_at(-1)', fn() => uuid_v7_at(-1));
fl_show('uuid_v4_batch("2")', fn() => uuid_v4_batch('2'), false);
fl_show('uuid_v7_bin_batch(0)', fn() => uuid_v7_bin_batch(0));
fl_show('fast_uuid_random_bytes([])', fn() => fast_uuid_random_bytes([]));

// Nested frameless calls pass temporaries.
var_dump(uuid_from_bin(uuid_to_bin('{6BA7B810-9DAD-11D1-80B4-00C04FD430C8}')));

// Coercion works on a copy: the caller's (referenced) variable keeps its type.
$n = 123;
$r = &$n;
var_dump(uuid_is_valid($r), $n);

// A discarded result still throws, and the trace names the function.
try {
    uuid_to_bin('x');
} catch (FastUuid\Exception\InvalidUuidStringException $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', $e->getTrace()[0]['function'], "\n";
}

// A deprecation promoted to an exception replaces the call's result.
set_error_handler(function (int $no, string $msg): bool { throw new ErrorException($msg, 0, $no); });
try {
    $x = uuid_to_bin(null);
} catch (Throwable $e) {
    echo get_class($e), ': ', $e->getMessage(), ', previous: ', var_export($e->getPrevious(), true), ', result set: ', var_export(isset($x), true), "\n";
}
restore_error_handler();
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
\fl_show('Fl: uuid_v7_at("x")', fn() => uuid_v7_at('x'));
var_dump(uuid_from_bin(uuid_to_bin('6ba7b810-9dad-11d1-80b4-00c04fd430c8')));
}

namespace Fl\Shadow {
// A namespaced function of the same name wins over the frameless global.
function uuid_v4(): string { return 'shadowed'; }
var_dump(uuid_v4());
}
?>
--EXPECT--
global: 230 calls, 123 distinct outcomes, 0 mismatches
uuid_v4(1) => ArgumentCountError: uuid_v4() expects exactly 0 arguments, 1 given
uuid_to_bin() => ArgumentCountError: uuid_to_bin() expects exactly 1 argument, 0 given
uuid_to_bin(int) => FastUuid\Exception\InvalidUuidStringException: Invalid UUID
uuid_to_bin(null) => E_DEPRECATED: uuid_to_bin(): Passing null to parameter #1 ($uuid) of type string is deprecated | FastUuid\Exception\InvalidUuidStringException: Invalid UUID
uuid_to_bin(Stringable) => string 0x6ba7b8109dad11d180b400c04fd430c8
uuid_is_valid(throwing Stringable) => RuntimeException: __toString failed
uuid_v5(DNS, www.example.com) => string "2ed6657d-e927-568b-95e1-2665a8aea6a2"
uuid_v3(bad ns) => FastUuid\Exception\InvalidArgumentException: Invalid namespace
uuid_v8(15 bytes) => FastUuid\Exception\InvalidArgumentException: uuid8 requires 16 bytes
uuid_v7_at(3.5) => E_DEPRECATED: Implicit conversion from float 3.5 to int loses precision | string(36)
uuid_v7_at(-1) => FastUuid\Exception\InvalidArgumentException: v7 millisecond timestamp out of range (0 .. 281474976710655)
uuid_v4_batch("2") => array(2) of string(36)
uuid_v7_bin_batch(0) => FastUuid\Exception\InvalidArgumentException: count must be > 0
fast_uuid_random_bytes([]) => TypeError: fast_uuid_random_bytes(): Argument #1 ($length) must be of type int, array given
string(36) "6ba7b810-9dad-11d1-80b4-00c04fd430c8"
bool(false)
int(123)
FastUuid\Exception\InvalidUuidStringException: Invalid UUID in uuid_to_bin
ErrorException: uuid_to_bin(): Passing null to parameter #1 ($uuid) of type string is deprecated, previous: NULL, result set: false
namespaced: 230 calls, 123 distinct outcomes, 0 mismatches
Fl: uuid_to_bin(int) => FastUuid\Exception\InvalidUuidStringException: Invalid UUID
Fl: uuid_v7_at("x") => TypeError: uuid_v7_at(): Argument #1 ($unixMillis) must be of type int, string given
string(36) "6ba7b810-9dad-11d1-80b4-00c04fd430c8"
string(8) "shadowed"
