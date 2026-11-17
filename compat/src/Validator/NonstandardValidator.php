<?php

declare(strict_types=1);

namespace FastUuid\Compat\Validator;

/**
 * Accepts the same strings as GenericValidator, which does not check the version or variant either.
 *
 * @deprecated Use GenericValidator.
 */
final class NonstandardValidator extends GenericValidator
{
    public function validate(string $uuid): bool
    {
        return \uuid_is_valid($uuid, true);
    }
}
