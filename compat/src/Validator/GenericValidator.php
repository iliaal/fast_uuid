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
        $uuid = \str_replace(['urn:', 'uuid:', 'URN:', 'UUID:', '{', '}'], '', $uuid);

        return $uuid === '00000000-0000-0000-0000-000000000000'
            || \preg_match('/' . self::PATTERN . '/Di', $uuid) === 1;
    }
}
