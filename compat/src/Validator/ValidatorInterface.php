<?php

declare(strict_types=1);

namespace FastUuid\Compat\Validator;

/** Mirrors Ramsey\Uuid\Validator\ValidatorInterface. */
interface ValidatorInterface
{
    public function getPattern(): string;

    public function validate(string $uuid): bool;
}
