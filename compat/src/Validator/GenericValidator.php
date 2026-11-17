<?php

declare(strict_types=1);

namespace FastUuid\Compat\Validator;

/**
 * Validates strings as UUIDs of any variant, as ramsey/uuid 4.10's GenericValidator does.
 *
 * Accepts the 8-4-4-4-12 form in any letter case, optionally after "urn:", "uuid:", or "urn:uuid:" (any letter
 * case), optionally inside one pair of braces. The version and variant are not checked; Rfc4122\Validator checks
 * them. The core \FastUuid\Uuid::isValid() accepts more forms, such as 32 hex digits without hyphens.
 *
 * getPattern() returns the grammar of the UUID itself; validate() removes the prefixes and braces first.
 */
class GenericValidator implements ValidatorInterface
{
    private const VALID_PATTERN = '\A[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}\z';

    /** The longest valid form: "urn:uuid:", braces, and 36 characters. */
    private const MAX_LENGTH = 47;

    public function getPattern(): string
    {
        return self::VALID_PATTERN;
    }

    public function validate(string $uuid): bool
    {
        // ext-fast_uuid's strict mode accepts exactly these forms. A subclass may override stripWrappers() or
        // isCanonicalShape(), so only the exact class takes it.
        if (static::class === self::class) {
            return \uuid_is_valid($uuid, true);
        }

        $inner = static::stripWrappers($uuid);

        return $inner !== null && static::isCanonicalShape($inner);
    }

    /**
     * Removes one "urn:" prefix, then one "uuid:" prefix (any letter case), then one pair of braces; returns null
     * when the string is longer than any valid form.
     */
    protected static function stripWrappers(string $uuid): ?string
    {
        if (\strlen($uuid) > self::MAX_LENGTH) {
            return null;
        }
        if (\strncasecmp($uuid, 'urn:', 4) === 0) {
            $uuid = \substr($uuid, 4);
        }
        if (\strncasecmp($uuid, 'uuid:', 5) === 0) {
            $uuid = \substr($uuid, 5);
        }
        if (\strlen($uuid) >= 2 && $uuid[0] === '{' && $uuid[-1] === '}') {
            $uuid = \substr($uuid, 1, -1);
        }

        return $uuid;
    }

    /** The 8-4-4-4-12 form, without a version or variant constraint. */
    protected static function isCanonicalShape(string $inner): bool
    {
        return \strlen($inner) === 36 && \preg_match('/' . self::VALID_PATTERN . '/', $inner) === 1;
    }
}
