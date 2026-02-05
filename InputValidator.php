<?php

namespace CCTC\DataEntryLogModule;

/**
 * Input validation and sanitization helper class for GET/POST parameters.
 */
class InputValidator
{
    /**
     * Sanitizes a string input by trimming whitespace and removing null bytes.
     * Returns the default value if input is not set or empty.
     */
    public static function sanitizeString(?string $value, string $default = ""): string
    {
        if ($value === null || $value === "") {
            return $default;
        }
        // Remove null bytes and trim whitespace
        $sanitized = str_replace("\0", "", trim($value));
        return $sanitized;
    }

    /**
     * Sanitizes a string input, returning null if empty.
     */
    public static function sanitizeStringOrNull(?string $value): ?string
    {
        if ($value === null || $value === "") {
            return null;
        }
        return str_replace("\0", "", trim($value));
    }

    /**
     * Validates and returns an integer within the specified range.
     * Returns the default value if input is invalid or out of range.
     */
    public static function validateInt($value, int $default, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        if ($value === null || $value === "") {
            return $default;
        }

        if (!is_numeric($value)) {
            return $default;
        }

        $intVal = (int)$value;

        if ($intVal < $min || $intVal > $max) {
            return $default;
        }

        return $intVal;
    }

    /**
     * Validates and returns an integer, or null if not provided.
     * Returns null if input is invalid.
     */
    public static function validateIntOrNull($value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): ?int
    {
        if ($value === null || $value === "") {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $intVal = (int)$value;

        if ($intVal < $min || $intVal > $max) {
            return null;
        }

        return $intVal;
    }

    /**
     * Validates a value against a whitelist of allowed values.
     * Returns the default if the value is not in the allowed list.
     */
    public static function validateEnum(?string $value, array $allowedValues, string $default): string
    {
        if ($value === null || !in_array($value, $allowedValues, true)) {
            return $default;
        }
        return $value;
    }

    /**
     * Validates a date string matches the expected format.
     * Returns null if the date is invalid.
     */
    public static function validateDateString(?string $value, string $format): ?string
    {
        if ($value === null || $value === "") {
            return null;
        }

        $sanitized = self::sanitizeString($value);
        $dateTime = \DateTime::createFromFormat($format, $sanitized);

        // Check if the date was parsed correctly and matches the input
        if ($dateTime === false || $dateTime->format($format) !== $sanitized) {
            return null;
        }

        return $sanitized;
    }

    /**
     * Validates a date string, returning a default if invalid.
     */
    public static function validateDateStringWithDefault(?string $value, string $format, ?string $default): ?string
    {
        $validated = self::validateDateString($value, $format);
        return $validated !== null ? $validated : $default;
    }

    /**
     * Gets a sanitized GET parameter string value.
     */
    public static function getStringParam(string $key, string $default = ""): string
    {
        return self::sanitizeString($_GET[$key] ?? null, $default);
    }

    /**
     * Gets a sanitized GET parameter string value or null.
     */
    public static function getStringParamOrNull(string $key): ?string
    {
        return self::sanitizeStringOrNull($_GET[$key] ?? null);
    }

    /**
     * Gets a validated GET parameter integer value.
     */
    public static function getIntParam(string $key, int $default, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        return self::validateInt($_GET[$key] ?? null, $default, $min, $max);
    }

    /**
     * Gets a validated GET parameter integer value or null.
     */
    public static function getIntParamOrNull(string $key, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): ?int
    {
        return self::validateIntOrNull($_GET[$key] ?? null, $min, $max);
    }

    /**
     * Gets a validated GET parameter enum value.
     */
    public static function getEnumParam(string $key, array $allowedValues, string $default): string
    {
        return self::validateEnum($_GET[$key] ?? null, $allowedValues, $default);
    }
}
