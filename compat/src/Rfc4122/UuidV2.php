<?php

declare(strict_types=1);

namespace FastUuid\Compat\Rfc4122;

use FastUuid\Compat\AbstractUuid;
use FastUuid\Compat\Type\Integer as IntegerObject;

/**
 * RFC 4122 DCE Security (version 2). The local identifier occupies time_low
 * (bytes 0-3, big-endian) and the local domain occupies clock_seq_low (byte 9).
 */
final class UuidV2 extends AbstractUuid
{
    public function getLocalDomain(): int
    {
        return \ord($this->core->getBytes()[9]);
    }

    public function getLocalIdentifier(): IntegerObject
    {
        return new IntegerObject(\unpack('N', \substr($this->core->getBytes(), 0, 4))[1]);
    }
}
