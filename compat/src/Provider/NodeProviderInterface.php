<?php

declare(strict_types=1);

namespace FastUuid\Compat\Provider;

/** Mirrors Ramsey\Uuid\Provider\NodeProviderInterface. Returns 6 raw node bytes. */
interface NodeProviderInterface
{
    public function getNode(): string;
}
