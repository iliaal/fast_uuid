<?php

declare(strict_types=1);

namespace FastUuid\Compat\Validator;

/**
 * Lax validator for nonstandard UUIDs: accepts the same canonical 8-4-4-4-12
 * shape and urn:/{} wrapper grammar as GenericValidator, WITHOUT constraining
 * the version or variant nibbles. Future/special forms the strict validator
 * rejects (non-RFC variants, unassigned versions, max UUID) validate here.
 *
 * getPattern() (inherited) returns the inner canonical grammar; like the
 * parent, validate() strips wrappers before matching.
 */
final class NonstandardValidator extends GenericValidator
{
    public function validate(string $uuid): bool
    {
        $inner = static::stripWrappers($uuid);
        if ($inner === null) {
            return false;
        }

        return static::isCanonicalShape($inner);
    }
}
