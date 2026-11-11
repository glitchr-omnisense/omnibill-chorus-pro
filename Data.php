<?php

namespace Omnibill\ChorusPro;

use Omnisense\Contracts\Http\Data as KernelData;

/**
 * Chorus Pro's decoded answers read without trusting their shape: the
 * kernel's Data (get() by key or path, data(), list()...), and the reads that
 * keep PHP's casts of a scalar as they are ("" kept, nothing trimmed), for
 * the values the gateway turned into strings with (string) or numbers with
 * (int) before.
 *
 * @internal
 */
final readonly class Data extends KernelData
{
    /** (string) of the value: "" when absent or not a scalar. */
    public function text(string|int $key): string
    {
        return self::cast($this->get($key));
    }

    /** (string) of the value, null when absent. */
    public function textOrNull(string|int $key): ?string
    {
        $value = $this->get($key);

        return null === $value ? null : self::cast($value);
    }

    /** (string) of a scalar or a Stringable, "" for anything else. */
    public static function cast(mixed $value): string
    {
        return \is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }

    /**
     * A decoded JSON object as it is, its keys as PHP keeps them (an empty
     * array when it is not an array).
     *
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }
        $map = [];
        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }

    /** (int) of a scalar, 0 for anything else. */
    public static function integer(mixed $value): int
    {
        return \is_scalar($value) ? (int) $value : 0;
    }

    /**
     * A map of strings, each value as cast() makes it.
     *
     * @param array<mixed> $values
     *
     * @return array<string, string>
     */
    public static function textsByKey(array $values): array
    {
        return array_map(self::cast(...), self::map($values));
    }
}
