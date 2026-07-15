<?php

declare(strict_types=1);

namespace FastUuid\Compat;

use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;
use FastUuid\Compat\Validator\ValidatorInterface;

interface UuidFactoryInterface
{
    public function getValidator(): ValidatorInterface;
    public function uuid1(int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): UuidInterface;
    public function uuid2(
        int $localDomain,
        int|string|IntegerObject|null $localIdentifier = null,
        int|string|Hexadecimal|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface;
    public function uuid3(UuidInterface|string $ns, string $name): UuidInterface;
    public function uuid4(): UuidInterface;
    public function uuid5(UuidInterface|string $ns, string $name): UuidInterface;
    public function uuid6(int|string|Hexadecimal|null $node = null, ?int $clockSeq = null): UuidInterface;
    public function fromString(string $uuid): UuidInterface;
    public function fromBytes(string $bytes): UuidInterface;
    public function fromInteger(string $integer): UuidInterface;
    public function fromDateTime(
        \DateTimeInterface $dateTime,
        int|string|Hexadecimal|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface;
}
