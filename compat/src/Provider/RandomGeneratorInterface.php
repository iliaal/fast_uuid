<?php

declare(strict_types=1);

namespace FastUuid\Compat\Provider;

/** Mirrors Ramsey\Uuid\Generator\RandomGeneratorInterface. Returns raw bytes. */
interface RandomGeneratorInterface
{
    public function generate(int $length): string;
}
