<?php

declare(strict_types=1);

namespace FastUuid\Compat\Rfc4122;

use FastUuid\Compat\Validator\ValidatorInterface;

/**
 * Validates strings as UUIDs of the RFC 9562 variant, as ramsey/uuid 4.10's Rfc4122\Validator does.
 *
 * Accepts the forms GenericValidator accepts when the version is 1 through 8 and the variant is RFC 9562, and the
 * nil and max UUIDs.
 */
class Validator implements ValidatorInterface
{
    private const VALID_PATTERN = '\A[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-'
        . '[1-8][0-9A-Fa-f]{3}-[ABab89][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}\z';

    private const NIL = '00000000-0000-0000-0000-000000000000';

    private const MAX = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

    public function getPattern(): string
    {
        return self::VALID_PATTERN;
    }

    public function validate(string $uuid): bool
    {
        if (!\uuid_is_valid($uuid, true)) {
            return false;
        }

        // The UUID is the last 36 characters, or the 36 before the closing brace.
        $end = $uuid[-1] === '}' ? -1 : 0;
        $version = $uuid[$end - 22];
        if ($version >= '1' && $version <= '8' && \str_contains('89abAB', $uuid[$end - 17])) {
            return true;
        }

        $inner = \strtolower(\substr($uuid, $end - 36, 36));

        return $inner === self::NIL || $inner === self::MAX;
    }
}
