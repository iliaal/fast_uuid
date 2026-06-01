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

        return \preg_match('/' . self::PATTERN . '/Di', $uuid) === 1;
    }
}
