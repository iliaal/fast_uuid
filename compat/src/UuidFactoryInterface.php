<?php

declare(strict_types=1);

namespace FastUuid\Compat;

use FastUuid\Compat\Codec\CodecInterface;
use FastUuid\Compat\Provider\NodeProviderInterface;
use FastUuid\Compat\Provider\RandomGeneratorInterface;
use FastUuid\Compat\Provider\TimeGeneratorInterface;
use FastUuid\Compat\Type\Hexadecimal;
use FastUuid\Compat\Type\Integer as IntegerObject;
use FastUuid\Compat\Validator\ValidatorInterface;

interface UuidFactoryInterface
{
    public function getRandomGenerator(): RandomGeneratorInterface;
    public function setRandomGenerator(RandomGeneratorInterface $generator): void;
    public function getTimeGenerator(): TimeGeneratorInterface;
    public function setTimeGenerator(TimeGeneratorInterface $generator): void;
    public function getNodeProvider(): NodeProviderInterface;
    public function setNodeProvider(NodeProviderInterface $provider): void;
    public function getValidator(): ValidatorInterface;
    public function setValidator(ValidatorInterface $validator): void;
    public function getCodec(): CodecInterface;
    public function setCodec(CodecInterface $codec): void;
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
    public function uuid7(int|\DateTimeInterface|null $dateTime = null): UuidInterface;
    public function uuid8(string $bytes): UuidInterface;
    public function fromString(string $uuid): UuidInterface;
    public function fromBytes(string $bytes): UuidInterface;
    public function fromInteger(string $integer): UuidInterface;
    public function fromHexadecimal(Hexadecimal|string $hex): UuidInterface;
    public function fromDateTime(
        \DateTimeInterface $dateTime,
        int|string|Hexadecimal|null $node = null,
        ?int $clockSeq = null,
    ): UuidInterface;
    public function wrap(\FastUuid\Uuid $core): UuidInterface;
}
