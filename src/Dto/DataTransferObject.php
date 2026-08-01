<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Dto;

use JsonSerializable;

/**
 * Lightweight base for readonly DTOs with public constructor-promoted fields.
 */
abstract readonly class DataTransferObject implements JsonSerializable
{
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        /** @phpstan-ignore new.static */
        return new static(...$data);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        /** @var array<string, mixed> $data */
        $data = get_object_vars($this);
        return $data;
    }

    /** @param array<string, mixed> $changes */
    public function with(array $changes): static
    {
        /** @phpstan-ignore new.static */
        return new static(...array_replace($this->toArray(), $changes));
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
