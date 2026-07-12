<?php

declare(strict_types=1);

namespace FastUuid\Compat\Validator;

/**
 * Lax validator for nonstandard-variant UUIDs: accepts the same wrapper
 * grammar as the core parser, without constraining the version or variant
 * nibbles.
 */
final class NonstandardValidator extends GenericValidator
{
}
