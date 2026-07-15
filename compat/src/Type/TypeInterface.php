<?php

declare(strict_types=1);

namespace FastUuid\Compat\Type;

interface TypeInterface extends \JsonSerializable, \Serializable
{
    public function toString(): string;
    public function __toString(): string;
}
