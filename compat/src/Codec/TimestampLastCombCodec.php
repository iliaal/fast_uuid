<?php

declare(strict_types=1);

namespace FastUuid\Compat\Codec;

/**
 * COMB layout with the 48-bit timestamp in the trailing bytes (the position a
 * COMB generator writes it). This is the identity over the canonical codec;
 * its counterpart {@see TimestampFirstCombCodec} moves the timestamp to the
 * front for DB index locality. Mirrors Ramsey\Uuid\Codec\TimestampLastCombCodec.
 */
final class TimestampLastCombCodec extends StringCodec
{
}
