<?php

declare(strict_types=1);

namespace FastUuid\Compat\Provider;

/**
 * Default random source: the extension's batched CSPRNG via
 * fast_uuid_random_bytes(), so a swapped-in generator still rides the same
 * amortized getrandom() buffer.
 */
final class DefaultRandomGenerator implements RandomGeneratorInterface
{
    public function generate(int $length): string
    {
        return \fast_uuid_random_bytes($length);
    }
}
