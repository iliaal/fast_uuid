<?php

declare(strict_types=1);

namespace FastUuid\Compat\Validator;

/**
 * Lax validator for nonstandard-variant UUIDs: accepts any 8-4-4-4-12 hex
 * shape without constraining the version or variant nibbles. Mirrors
 * Ramsey\Uuid\Nonstandard\Validator.
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
        $uuid = \str_replace(['urn:', 'uuid:', 'URN:', 'UUID:', '{', '}'], '', $uuid);

        return \strlen($uuid) === 36
            && $uuid[8] === '-'
            && $uuid[13] === '-'
            && $uuid[18] === '-'
            && $uuid[23] === '-'
            && \FastUuid\Uuid::isValid($uuid);
    }
}
