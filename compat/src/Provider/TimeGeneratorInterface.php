<?php

declare(strict_types=1);

namespace FastUuid\Compat\Provider;

/**
 * Mirrors Ramsey\Uuid\Generator\TimeGeneratorInterface. Returns the 16 raw
 * bytes of a time-based (v1) UUID for the given node / clock sequence.
 */
interface TimeGeneratorInterface
{
    public function generate(int|string|null $node = null, ?int $clockSeq = null): string;
}
