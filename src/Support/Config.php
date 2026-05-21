<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Support;

/**
 * Type-safe access to the package configuration.
 *
 * Laravel's global config() helper returns mixed, which forces every call site
 * to defend against unexpected shapes. This helper centralises the coercion so
 * the rest of the package can rely on concrete scalar and array types.
 */
final class Config
{
    /**
     * Get the configuration value as a string.
     */
    public static function string(string $key, string $default = ''): string
    {
        $value = config($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Get the configuration value as an integer.
     */
    public static function int(string $key, int $default = 0): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get the configuration value as a boolean.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = config($key, $default);

        return is_scalar($value) ? (bool) $value : $default;
    }

    /**
     * Get the configuration value as an array.
     *
     * @param array<array-key, mixed> $default
     *
     * @return array<array-key, mixed>
     */
    public static function array(string $key, array $default = []): array
    {
        $value = config($key, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * Get the configuration value as a list of strings.
     *
     * @return array<int, string>
     */
    public static function stringList(string $key): array
    {
        $values = self::array($key);

        $list = [];

        foreach ($values as $value) {
            if (is_scalar($value)) {
                $list[] = (string) $value;
            }
        }

        return $list;
    }
}
