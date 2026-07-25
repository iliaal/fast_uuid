<?php

declare(strict_types=1);

namespace FastUuid\Compat\Validator;

/**
 * RFC 4122 / RFC 9562 string validator. Mirrors
 * Ramsey\Uuid\Validator\GenericValidator: accepts the canonical 8-4-4-4-12
 * form (case-insensitive) plus the nil UUID, after stripping urn:/{} wrappers.
 */
class GenericValidator implements ValidatorInterface
{
    private const PATTERN = '\A[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}\z';

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

        return \strlen($uuid) === 36 && \FastUuid\Uuid::isValid($uuid);
    }
}
