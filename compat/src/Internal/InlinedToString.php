<?php

declare(strict_types=1);

namespace FastUuid\Compat\Internal;

/**
 * __toString() for the final wrapper classes: the body of
 * AbstractUuid::toString() without its extra userland call. Only final
 * wrappers may use it. In an open class a subclass overriding toString() must
 * still be honoured by the inherited AbstractUuid::__toString().
 */
trait InlinedToString
{
    public function __toString(): string
    {
        return $this->codec === null
            ? $this->core->toString()
            : $this->codec->encode($this);
    }
}
