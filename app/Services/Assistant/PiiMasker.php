<?php

namespace App\Services\Assistant;

/**
 * Masks national ID / birth-registration numbers and phone numbers before any
 * text or data is sent to the language model (docs/DECISIONS.md D-014).
 */
class PiiMasker
{
    public const MASK = '[masked]';

    public function maskText(string $text): string
    {
        $text = preg_replace('/(?<![\d])(?:\+?880|0)1[3-9]\d{8}(?![\d])/', self::MASK, $text) ?? $text;

        return preg_replace('/(?<![\d])(?:\d{17}|\d{13}|\d{10})(?![\d])/', self::MASK, $text) ?? $text;
    }

    /**
     * Masks recursively: strings by pattern, values under sensitive keys entirely.
     */
    public function mask(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key) && $value !== null && $value !== '') {
            return self::MASK;
        }

        if (is_string($value)) {
            return $this->maskText($value);
        }

        if (is_array($value)) {
            $out = [];

            foreach ($value as $k => $item) {
                $out[$k] = $this->mask($item, is_string($k) ? $k : null);
            }

            return $out;
        }

        return $value;
    }

    protected function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (config('assistant.mask_keys', []) as $sensitive) {
            if ($key === $sensitive || str_ends_with($key, '_'.$sensitive)) {
                return true;
            }
        }

        return false;
    }
}
