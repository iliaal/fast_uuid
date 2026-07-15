<?php

declare(strict_types=1);

namespace FastUuid\Compat\Type;

interface NumberInterface extends TypeInterface
{
    public function isNegative(): bool;
}
