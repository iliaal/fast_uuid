<?php

declare(strict_types=1);

namespace FastUuid\Compat\Validator;

/**
 * RFC 4122 / RFC 9562 string validator. Mirrors
 * Ramsey\Uuid\Validator\GenericValidator: accepts the canonical 8-4-4-4-12
 * form (case-insensitive) plus the nil UUID, after stripping urn:/{} wrappers.
 */
final class GenericValidator implements ValidatorInterface
{
    private const PATTERN = '\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z';

    public function getPattern(): string
    {
        return self::PATTERN;
    }

    public function validate(string $uuid): bool
    {
        if (\strlen($uuid) >= 9 && \strncasecmp($uuid, 'urn:uuid:', 9) === 0) {
            $uuid = \substr($uuid, 9);
        }
        if (\strlen($uuid) >= 2 && $uuid[0] === '{' && $uuid[\strlen($uuid) - 1] === '}') {
            $uuid = \substr($uuid, 1, -1);
        }

        return $uuid === '00000000-0000-0000-0000-000000000000'
            || (\strlen($uuid) === 36
                && $uuid[8] === '-'
                && $uuid[13] === '-'
                && $uuid[18] === '-'
                && $uuid[23] === '-'
                && \FastUuid\Uuid::isValid($uuid));
    }
}
