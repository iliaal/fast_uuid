<?php

declare(strict_types=1);

namespace FastUuid\Compat\Validator;

/**
 * Strict RFC 4122 / RFC 9562 string validator.
 *
 * Accepts the canonical 8-4-4-4-12 form (case-insensitive) with an RFC
 * version nibble (1..8) and an RFC variant nibble (8/9/a/b), after stripping
 * urn:/{} wrappers in any composition up to depth 2 (mirroring the C parser:
 * {urn:uuid:...} and urn:uuid:{...} are valid). The nil UUID is exempt from
 * the nibble checks as the all-zero special case (ramsey parity).
 *
 * For the shape-only variant without version/variant constraints see
 * NonstandardValidator. For the more permissive core parser (additionally
 * bare 32-hex, no nibble constraints) see \FastUuid\Uuid::isValid().
 *
 * getPattern() returns the INNER canonical grammar only: validate()
 * additionally strips wrappers before matching, so wrapper input validates
 * while matching the pattern only after unwrapping.
 */
class GenericValidator implements ValidatorInterface
{
    private const PATTERN = '\A[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}\z';

    private const NIL = '00000000-0000-0000-0000-000000000000';

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function validate(string $uuid): bool
    {
        $inner = static::stripWrappers($uuid);
        if ($inner === null || !static::isCanonicalShape($inner)) {
            return false;
        }
        if ($inner === self::NIL) {
            return true;
        }
        // Strict nibbles: version 1..8, variant 10xx.
        if ($inner[14] < '1' || $inner[14] > '8') {
            return false;
        }

        return \strpos('89abAB', $inner[19]) !== false;
    }

    /**
     * Strip urn:/{} wrappers in any composition up to depth 2, mirroring
     * the C parser (fu_parse). Returns null when the input is implausibly
     * long before any substr() copy: the longest valid wrapped form is
     * "urn:uuid:{...}" = 9 + 1 + 36 + 1 = 47 bytes.
     */
    protected static function stripWrappers(string $uuid): ?string
    {
        if (\strlen($uuid) > 47) {
            return null;
        }
        for ($pass = 0; $pass < 2; $pass++) {
            if (\strlen($uuid) >= 9 && \strncasecmp($uuid, 'urn:uuid:', 9) === 0) {
                $uuid = \substr($uuid, 9);
            }
            if (\strlen($uuid) >= 2 && $uuid[0] === '{' && $uuid[\strlen($uuid) - 1] === '}') {
                $uuid = \substr($uuid, 1, -1);
            }
        }

        return $uuid;
    }

    /** Inner canonical 8-4-4-4-12 shape, no version/variant constraint. */
    protected static function isCanonicalShape(string $inner): bool
    {
        return \strlen($inner) === 36 && \preg_match('/' . self::PATTERN . '/', $inner) === 1;
    }
}
