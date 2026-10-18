<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Json;

use JsonException;

/**
 * JSON helpers with explicit success and failure contracts.
 */
final class Json
{
    private const int MAX_DEPTH = 512;

    private function __construct()
    {
    }

    /**
     * Encodes a value as JSON, throwing on unsupported structures.
     *
     * @param int $flags Any {@see json_encode} flags except {@see JSON_THROW_ON_ERROR}, which is always enabled.
     *
     * @throws JsonException When the value cannot be encoded.
     */
    public static function encode(mixed $value, int $flags = 0): string
    {
        return json_encode($value, $flags | JSON_THROW_ON_ERROR, self::MAX_DEPTH);
    }

    /**
     * Decodes a JSON document, throwing on malformed input.
     *
     * @param bool $associative When true, objects are decoded as associative arrays.
     *
     * @throws JsonException When the payload is not valid JSON.
     */
    public static function decode(string $json, bool $associative = true): mixed
    {
        return json_decode($json, $associative, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
    }

    /**
     * Reports whether a string is a valid JSON document without materializing it.
     */
    public static function isValid(string $json): bool
    {
        return json_validate($json, self::MAX_DEPTH);
    }
}
