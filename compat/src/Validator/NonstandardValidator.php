<?php

declare(strict_types=1);

namespace FastUuid\Compat\Validator;

/**
 * Lax validator for nonstandard-variant UUIDs: accepts the same wrapper
 * grammar as the core parser, without constraining the version or variant
 * nibbles.
 */
final class NonstandardValidator implements ValidatorInterface
{
    private const PATTERN = '\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z';

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function validate(string $uuid): bool
    {
        // Reject implausibly long input before any substr() copy: the longest
        // valid wrapped form is "urn:uuid:{...}" = 9 + 1 + 36 + 1 = 47 bytes.
        if (\strlen($uuid) > 47) {
            return false;
        }
        if (\strlen($uuid) >= 9 && \strncasecmp($uuid, 'urn:uuid:', 9) === 0) {
            $uuid = \substr($uuid, 9);
        }
        if (\strlen($uuid) >= 2 && $uuid[0] === '{' && $uuid[\strlen($uuid) - 1] === '}') {
            $uuid = \substr($uuid, 1, -1);
        }

        return \strlen($uuid) === 36
            && $uuid[8] === '-'
            && $uuid[13] === '-'
            && $uuid[18] === '-'
            && $uuid[23] === '-'
            && \FastUuid\Uuid::isValid($uuid);
    }
}
