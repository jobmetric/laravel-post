<?php

namespace JobMetric\Post\Support;

/** Compare structured content independently of object-key ordering. */
final class ContentFingerprint
{
    /**
     * Hash normalized content without reordering lists.
     *
     * @param array<string, mixed> $content
     *
     * @return string
     */
    public static function hash(array $content): string
    {
        return hash('sha256', json_encode(self::normalize($content), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Sort object keys recursively while preserving array sequence.
     *
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function normalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = self::normalize($item);
            }
        }

        return $value;
    }
}
