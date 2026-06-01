<?php

declare(strict_types=1);

namespace FastUuid\Compat\Provider;

/** Random 6-byte node with the multicast bit set (RFC 9562 §6.10). */
final class RandomNodeProvider implements NodeProviderInterface
{
    public function getNode(): string
    {
        $node = \fast_uuid_random_bytes(6);
        $node[0] = \chr(\ord($node[0]) | 0x01);
        return $node;
    }
}
