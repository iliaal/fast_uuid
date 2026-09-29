--TEST--
Two-argument frameless calls keep both argument values alive across coercion side effects
--EXTENSIONS--
fast_uuid
--FILE--
<?php
// A direct call passes compiled variables by pointer, not by copy. Coercing
// one argument can run userland (an error handler on the null-argument
// deprecation, or __toString) that reassigns the other argument's variable.
// The call must see the values it was given, as the ZPP path does.

final class Reassign {
    public function __construct(private string $var, private mixed $to, private string $str) {}
    public function __toString(): string { $GLOBALS[$this->var] = $this->to; return $this->str; }
}

$ns = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
$expected = uuid_v5($ns, '');

// Coercing $name (null) runs the handler, which frees $a's string.
set_error_handler(function (): bool { $GLOBALS['a'] = null; return true; });
function via_handler(): string {
    $GLOBALS['a'] = strtoupper('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    return uuid_v5($GLOBALS['a'], null);
}
function via_handler_cv(): array {
    global $a;
    $r = [];
    $a = strtoupper('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    $r[] = uuid_v5($a, null);
    $a = strtoupper('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    $r[] = uuid_v3_bin($a, null);
    $a = strtoupper('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    $r[] = uuid_v5_bin($a, null);
    $a = strtoupper('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    $r[] = uuid_v3($a, null);
    var_dump($a);
    return $r;
}
var_dump(via_handler() === $expected);
$r = via_handler_cv();
var_dump($r[0] === $expected);
var_dump($r[1] === uuid_v3_bin($ns, ''), $r[2] === uuid_v5_bin($ns, ''), $r[3] === uuid_v3($ns, ''));
restore_error_handler();

// Coercing $name (Stringable) reassigns $a first.
function via_tostring(): string {
    global $a;
    $a = strtoupper('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    return uuid_v5($a, new Reassign('a', 1, 'www.example.com'));
}
var_dump(via_tostring() === uuid_v5($ns, 'www.example.com'));

// Coercing $ns (Stringable) reassigns $b; the call still uses the old $b.
function via_tostring_first(): string {
    global $b;
    $b = str_repeat('x', 40);
    return uuid_v5(new Reassign('b', 'changed', '6ba7b810-9dad-11d1-80b4-00c04fd430c8'), $b);
}
var_dump(via_tostring_first() === uuid_v5($ns, str_repeat('x', 40)));
var_dump($b);
?>
--EXPECT--
bool(true)
NULL
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
string(7) "changed"
