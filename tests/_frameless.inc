<?php
/* Parity harness for the procedural API. On PHP 8.4+ a direct call such as
   uuid_to_bin($a) compiles to a frameless handler (zflf_*), while a dynamic
   call $f(...$a) always goes through the ZPP entry point (zif_*). Both call
   sites must live in the test file so they carry its strict_types and
   namespace; this file only drives them and compares the outcomes. */

final class FlStringable {
    public function __construct(private string $value) {}
    public function __toString(): string { return $this->value; }
}

final class FlThrowingStringable {
    public function __toString(): string { throw new RuntimeException('__toString failed'); }
}

function fl_string_inputs(): array {
    return [
        'uuid' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
        'bad' => 'not-a-uuid',
        '16 bytes' => "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f",
        'int' => 123,
        'float' => 1.5,
        'bool' => true,
        'null' => null,
        'array' => [],
        'Stringable' => new FlStringable('6ba7b811-9dad-11d1-80b4-00c04fd430c8'),
        'throwing Stringable' => new FlThrowingStringable(),
        'stdClass' => new stdClass(),
    ];
}

function fl_long_inputs(): array {
    return [
        'int' => 3,
        'zero' => 0,
        'negative' => -1,
        'PHP_INT_MAX' => PHP_INT_MAX,
        'numeric string' => '3',
        'leading-numeric string' => '3 apples',
        'non-numeric string' => 'three',
        'integral float' => 3.0,
        'fractional float' => 3.5,
        'null' => null,
        'array' => [],
    ];
}

/* function => [input kind, deterministic output?] */
function fl_signatures(): array {
    $gen = ['none', false];
    return [
        'uuid_v1' => $gen, 'uuid_v1_bin' => $gen,
        'uuid_v4' => $gen, 'uuid_v4_bin' => $gen,
        'uuid_v4_fast' => $gen, 'uuid_v4_fast_bin' => $gen,
        'uuid_v6' => $gen, 'uuid_v6_bin' => $gen,
        'uuid_v7' => $gen, 'uuid_v7_bin' => $gen,
        'uuid_v7_at' => ['long', false], 'uuid_v7_at_bin' => ['long', false],
        'uuid_v3' => ['string2', true], 'uuid_v3_bin' => ['string2', true],
        'uuid_v5' => ['string2', true], 'uuid_v5_bin' => ['string2', true],
        'uuid_v8' => ['string', true], 'uuid_v8_bin' => ['string', true],
        'uuid_v4_batch' => ['long', false], 'uuid_v4_bin_batch' => ['long', false],
        'uuid_v7_batch' => ['long', false], 'uuid_v7_bin_batch' => ['long', false],
        'uuid_to_bin' => ['string', true],
        'uuid_from_bin' => ['string', true],
        'uuid_is_valid' => ['string', true],
        'fast_uuid_random_bytes' => ['long', false],
    ];
}

function fl_shape(mixed $r, bool $deterministic): string {
    if (is_string($r)) {
        if (!$deterministic) {
            return 'string(' . strlen($r) . ')';
        }
        return preg_match('/^[\x20-\x7e]*$/', $r) ? "string \"$r\"" : 'string 0x' . bin2hex($r);
    }
    if (is_bool($r)) {
        return $r ? 'true' : 'false';
    }
    if (is_array($r)) {
        $lens = array_unique(array_map(fn($s) => is_string($s) ? 'string(' . strlen($s) . ')' : get_debug_type($s), $r));
        return 'array(' . count($r) . ') of ' . implode(',', $lens);
    }
    return get_debug_type($r);
}

/* Return shape or throwable, preceded by every diagnostic the call raised. */
function fl_outcome(callable $call, bool $deterministic): string {
    $diag = [];
    set_error_handler(function (int $no, string $msg) use (&$diag): bool {
        $names = [E_DEPRECATED => 'E_DEPRECATED', E_WARNING => 'E_WARNING', E_NOTICE => 'E_NOTICE'];
        $diag[] = ($names[$no] ?? "E$no") . ': ' . $msg;
        return true;
    });
    try {
        $out = fl_shape($call(), $deterministic);
    } catch (Throwable $e) {
        $out = get_class($e) . ': ' . $e->getMessage();
    } finally {
        restore_error_handler();
    }
    return implode(' | ', array_merge($diag, [$out]));
}

/* Compare the direct (frameless-eligible) call against the dynamic call for
   every function and input. Prints each mismatch, then a deterministic tally. */
function fl_run(string $label, array $direct, callable $dynamic): void {
    $sigs = fl_signatures();
    if (array_keys($direct) != array_keys($sigs)) {
        echo "$label: direct-call table does not cover every procedural function\n";
        return;
    }
    $checked = 0;
    $mismatches = 0;
    $outcomes = [];
    foreach ($sigs as $fn => [$kind, $det]) {
        $argLists = match ($kind) {
            'none' => ['()' => []],
            'long' => array_map(fn($v) => [$v], fl_long_inputs()),
            'string' => array_map(fn($v) => [$v], fl_string_inputs()),
            'string2' => array_merge(
                array_combine(array_map(fn($k) => "ns=$k", array_keys(fl_string_inputs())),
                    array_map(fn($v) => [$v, 'www.example.com'], fl_string_inputs())),
                array_combine(array_map(fn($k) => "name=$k", array_keys(fl_string_inputs())),
                    array_map(fn($v) => ['6ba7b810-9dad-11d1-80b4-00c04fd430c8', $v], fl_string_inputs()))),
        };
        foreach ($argLists as $argLabel => $args) {
            $d = fl_outcome(fn() => $direct[$fn](...$args), $det);
            $y = fl_outcome(fn() => $dynamic($fn, $args), $det);
            $checked++;
            $outcomes[$d] = true;
            if ($d !== $y) {
                $mismatches++;
                echo "MISMATCH $label $fn($argLabel): direct=[$d] dynamic=[$y]\n";
            }
        }
    }
    printf("%s: %d calls, %d distinct outcomes, %d mismatches\n", $label, $checked, count($outcomes), $mismatches);
}

function fl_show(string $label, callable $call, bool $deterministic = true): void {
    echo $label, ' => ', fl_outcome($call, $deterministic), "\n";
}
