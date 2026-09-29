<?php

declare(strict_types=1);

namespace FastUuid\Compat\Rfc4122;

use FastUuid\Compat\AbstractUuid;
use FastUuid\Compat\Internal\InlinedToString;

final class UuidV4 extends AbstractUuid
{
    use InlinedToString;
}
