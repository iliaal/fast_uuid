<?php

declare(strict_types=1);

namespace FastUuid\Compat\Internal;

use FastUuid\Compat\Codec\CodecInterface;

/**
 * Declares AbstractUuid's state and nothing else. Minter shares this ancestor
 * so it can attach a core to a cloned wrapper without a method on
 * AbstractUuid's inheritable surface.
 *
 * @internal
 */
abstract class WrapperState
{
    protected \FastUuid\Uuid $core;
    protected ?CodecInterface $codec = null;
}
