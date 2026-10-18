<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Dto;

use JsonSerializable;
use ReflectionObject;

/**
 * Lightweight base for readonly DTOs with constructor-promoted fields.
 */
abstract readonly class DataTransferObject implements JsonSerializable
{
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        /** @phpstan-ignore new.static */
        return new static(...$data);
    }

    /**
     * Returns every non-static property, including private and protected ones.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        foreach ((new ReflectionObject($this))->getProperties() as $property) {
            if ($property->isStatic() || !$property->isInitialized($this)) {
                continue;
            }

            $data[$property->getName()] = $property->getValue($this);
        }

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
