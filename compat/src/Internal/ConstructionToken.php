<?php

declare(strict_types=1);

namespace FastUuid\Compat\Internal;

/** @internal Factory-only constructor capability. */
enum ConstructionToken
{
    case Trusted;
}
