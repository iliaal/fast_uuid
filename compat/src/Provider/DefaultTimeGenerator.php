<?php

declare(strict_types=1);

namespace FastUuid\Compat\Provider;

/** Default time source: the extension's v1 generator. */
final class DefaultTimeGenerator implements TimeGeneratorInterface
{
    public function generate(int|string|null $node = null, ?int $clockSeq = null): string
    {
        return \FastUuid\Uuid::uuid1($node, $clockSeq)->getBytes();
    }
}
