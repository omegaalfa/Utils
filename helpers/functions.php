<?php

declare(strict_types=1);

if (!function_exists('removerAcentos')) {
    /**
     * Transliterates common Latin characters and removes non-alphanumeric symbols.
     */
    function removerAcentos(string $string): string
    {
        $transliterated = \Omegaalfa\Utils\Helpers\LatinText::transliterate($string);
        $output = preg_replace('/[^A-Za-z0-9 ]+/', '', $transliterated);

        return $output ?? $transliterated;
    }
}

if (!function_exists('slugify')) {
    /**
     * Converts text to a lowercase, URL-friendly slug.
     */
    function slugify(string $string): string
    {
        $string = \Omegaalfa\Utils\Helpers\LatinText::transliterate($string);
        $string = preg_replace('/[^A-Za-z0-9]+/', '-', $string) ?? $string;

        return trim(strtolower($string), '-');
    }
}

if (!function_exists('normalizarEspacos')) {
    /**
     * Trims a string and collapses consecutive Unicode whitespace.
     */
    function normalizarEspacos(string $string): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($string));

        return $normalized ?? trim($string);
    }
}

if (!function_exists('filter_validate_int')) {
    /**
     * Validates an integer without silently altering the input.
     */
    function filter_validate_int(int|string $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT);

        return $validated === false ? null : $validated;
    }
}

if (!function_exists('filter_validate_float')) {
    /**
     * Validates a floating-point number without silently altering the input.
     */
    function filter_validate_float(int|float|string $value): ?float
    {
        $validated = filter_var($value, FILTER_VALIDATE_FLOAT);

        return $validated === false ? null : $validated;
    }
}

if (!function_exists('filter_validate_email')) {
    /**
     * Validates an email address and returns it unchanged when valid.
     */
    function filter_validate_email(string $email): ?string
    {
        $email = trim($email);
        $validated = filter_var($email, FILTER_VALIDATE_EMAIL);

        return $validated === false ? null : $validated;
    }
}

if (!function_exists('filter_validate_sql')) {
    /**
     * Validates a simple SQL identifier such as a table or column name.
     *
     * This function does not make SQL queries safe. Always bind values through
     * prepared statements and allow-list dynamic identifiers when possible.
     */
    function filter_validate_sql(string $identifier): ?string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier) === 1
            ? $identifier
            : null;
    }
}

if (!function_exists('timestamp')) {
    /**
     * Returns the current timestamp without changing PHP's global timezone.
     */
    function timestamp(string $timezone = 'America/Sao_Paulo'): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))
            ->format('Y-m-d H:i:s');
    }
}

if (!function_exists('arrayToJson')) {
    /**
     * @param array<array-key, mixed> $array
     * @throws \JsonException When encoding fails and $throw is true.
     */
    function arrayToJson(array $array, bool $throw = false): ?string
    {
        try {
            return json_encode($array, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            if ($throw) {
                throw $exception;
            }

            return null;
        }
    }
}

if (!function_exists('jsonToArray')) {
    /**
     * Decodes a JSON object or array without performing a redundant validation pass.
     *
     * @return array<array-key, mixed>|null
     * @throws \JsonException When decoding fails and $throw is true.
     * @throws \UnexpectedValueException When the JSON root is not an object or array
     *                                    and $throw is true.
     */
    function jsonToArray(string $json, bool $throw = false): ?array
    {
        if ($throw) {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } else {
            $decoded = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
        }

        if (!is_array($decoded)) {
            if ($throw) {
                throw new \UnexpectedValueException(
                    'The JSON root value must be an object or array.'
                );
            }

            return null;
        }

        return $decoded;
    }
}
