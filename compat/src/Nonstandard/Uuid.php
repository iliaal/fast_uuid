<?php

declare(strict_types=1);

namespace FastUuid\Compat\Nonstandard;

use FastUuid\Compat\AbstractUuid;

/** Non-RFC-4122-variant UUID (GUID-ordered or custom variants). */
final class Uuid extends AbstractUuid
{
    // Match Nonstandard\Fields::getVersion() and ramsey: nonstandard wrappers
    // do not surface a version nibble even when bytes have one.
    public function getVersion(): ?int
    {
        return null;
    }
}
