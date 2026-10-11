<?php

namespace JobMetric\Post\Support;

use InvalidArgumentException;

/**
 * Configure the media collections available to a post type.
 */
trait HasPostMediaOptions
{
    /**
     * Register a media collection and its cardinality and MIME policy.
     *
     * @param string $name
     * @param bool $multiple
     * @param array<array-key, mixed> $mimeTypes
     *
     * @return static
     */
    public function mediaCollection(string $name, bool $multiple = false, array $mimeTypes = ['image']): static
    {
        $this->assertFieldName($name);
        foreach ($mimeTypes as $mimeType) {
            if (! is_string($mimeType) || ! preg_match('~^[a-z0-9.+-]+(?:/[a-z0-9.+*-]+)?$~i', $mimeType)) {
                throw new InvalidArgumentException('Media MIME policies must contain valid MIME types or groups.');
            }
        }
        $collections = (array) $this->getTypeParam('media-collections', []);
        $collections[$name] = ['multiple' => $multiple, 'mimeTypes' => $mimeTypes];
        return $this->option('media-collections', $collections);
    }

    /**
     * Return the registered media collection definitions.
     *
     * @return array<array-key, mixed>
     */
    public function getMediaCollections(): array
    {
        return (array) $this->getTypeParam('media-collections', []);
    }

    /**
     * Enable a single primary image collection.
     *
     * @return static
     */
    public function baseMedia(): static
    {
        return $this->mediaCollection('base', false, ['image']);
    }

    /**
     * Determine whether the primary image collection is registered.
     *
     * @return bool
     */
    public function hasBaseMedia(): bool
    {
        return array_key_exists('base', $this->getMediaCollections());
    }
}
