<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Hasher;

use InvalidArgumentException;

/**
 * Password hashing and timing-safe comparison helpers.
 */
final class Hasher
{
    private function __construct()
    {
    }

    /**
     * Hashes a password using the selected native algorithm.
     *
     * @param string $algorithm A constant such as {@see PASSWORD_DEFAULT} or {@see PASSWORD_ARGON2ID}.
     * @param array<string, mixed> $options Algorithm options (for example `cost`, `memory_cost`, `time_cost`, `threads`).
     *
     * @throws InvalidArgumentException When the algorithm is not supported by this PHP build.
     */
    public static function passwordHash(
        string $password,
        string $algorithm = PASSWORD_DEFAULT,
        array $options = [],
    ): string {
        self::assertPasswordAlgorithm($algorithm);

        return password_hash($password, $algorithm, $options);
    }

    /**
     * Verifies a password against a stored hash in a timing-safe manner.
     */
    public static function passwordVerify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Reports whether a stored hash must be regenerated after a parameter change.
     *
     * @param array<string, mixed> $options Algorithm options previously used to produce the hash.
     *
     * @throws InvalidArgumentException When the algorithm is not supported by this PHP build.
     */
    public static function passwordNeedsRehash(
        string $hash,
        string $algorithm = PASSWORD_DEFAULT,
        array $options = [],
    ): bool {
        self::assertPasswordAlgorithm($algorithm);

        return password_needs_rehash($hash, $algorithm, $options);
    }

    /**
     * Produces an HMAC signature for a payload.
     *
     * @param string $algorithm Any algorithm listed by {@see hash_hmac_algos()}.
     * @param bool $raw When true, returns raw binary instead of hexadecimal text.
     *
     * @throws InvalidArgumentException When the key is empty or the algorithm is unsupported.
     */
    public static function hmac(
        string $payload,
        string $key,
        string $algorithm = 'sha256',
        bool $raw = false,
    ): string {
        self::assertHmac($key, $algorithm);

        return hash_hmac($algorithm, $payload, $key, $raw);
    }

    /**
     * Verifies an HMAC signature using a constant-time comparison.
     *
     * @throws InvalidArgumentException When the key is empty or the algorithm is unsupported.
     */
    public static function hmacVerify(
        string $payload,
        string $key,
        string $signature,
        string $algorithm = 'sha256',
        bool $raw = false,
    ): bool {
        return self::equals(self::hmac($payload, $key, $algorithm, $raw), $signature);
    }

    /**
     * Compares two strings without leaking timing information about the contents.
     *
     * @param string $known The expected value (for example a stored signature).
     * @param string $given The user-supplied value being validated.
     */
    public static function equals(string $known, string $given): bool
    {
        return hash_equals($known, $given);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function assertPasswordAlgorithm(string $algorithm): void
    {
        if (!in_array($algorithm, password_algos(), true)) {
            throw new InvalidArgumentException("Unsupported password hashing algorithm: {$algorithm}");
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function assertHmac(string $key, string $algorithm): void
    {
        if ($key === '' || str_contains($key, "\0")) {
            throw new InvalidArgumentException('HMAC key cannot be empty or contain NUL bytes.');
        }

        if (!in_array($algorithm, hash_hmac_algos(), true)) {
            throw new InvalidArgumentException("Unsupported HMAC algorithm: {$algorithm}");
        }
    }
}
