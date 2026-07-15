<?php

declare(strict_types=1);

namespace FastUuid\Compat\Rfc4122;

use FastUuid\Compat\AbstractUuid;

/** RFC 9562 / RFC 4122 — UuidV6. */
final class UuidV6 extends AbstractUuid
{
    /**
     * Convert this v6 UUID to its equivalent v1 (gregorian-ordered) form by
     * restoring the v1 timestamp field order. Mirrors ramsey's UuidV6::toUuidV1.
     */
    public function toUuidV1(): UuidV1
    {
        $hex = \bin2hex($this->core->getBytes());
        // v6 timestamp is most-significant-first: high 48 bits at [0:12], the
        // version nibble at [12], the low 12 bits at [13:16].
        $ts = \substr($hex, 0, 12) . \substr($hex, 13, 3); // 15 hex = 60-bit ts
        $timeHi  = \substr($ts, 0, 3);
        $timeMid = \substr($ts, 3, 4);
        $timeLow = \substr($ts, 7, 8);
        $v1hex = $timeLow . $timeMid . '1' . $timeHi . \substr($hex, 16);
        return new UuidV1(\FastUuid\Uuid::fromHexadecimal($v1hex), $this->codec);
    }

    /**
     * Build a v6 UUID from a v1 UUID by reordering the timestamp bytes
     * most-significant-first. Mirrors ramsey's UuidV6::fromUuidV1.
     */
    public static function fromUuidV1(UuidV1 $uuid): self
    {
        $hex = \bin2hex($uuid->getCore()->getBytes());
        // v1: time_low[0:8], time_mid[8:12], time_hi_and_version[12:16].
        $ts = \substr($hex, 13, 3) . \substr($hex, 8, 4) . \substr($hex, 0, 8); // 15 hex, msb-first
        $v6hex = \substr($ts, 0, 12) . '6' . \substr($ts, 12, 3) . \substr($hex, 16);
        return new self(\FastUuid\Uuid::fromHexadecimal($v6hex), $uuid->codec);
    }
}
