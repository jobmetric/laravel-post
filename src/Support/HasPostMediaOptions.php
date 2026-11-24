<?php

namespace JobMetric\Post\Support;

use InvalidArgumentException;
use JobMetric\Form\Form;
use JobMetric\Form\FormBuilder;

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
     * Register a form describing metadata for each item in a media collection.
     *
     * @param string $collection
     * @param Form|FormBuilder $form
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function mediaItemForm(string $collection, Form|FormBuilder $form): static
    {
        if (! array_key_exists($collection, $this->getMediaCollections())) {
            throw new InvalidArgumentException('Register the media collection before its item form.');
        }

        $forms = $this->getTypeParam('media-item-forms', []);
        $forms[$collection] = $form;

        return $this->option('media-item-forms', $forms);
    }

    /**
     * Return the item form for a collection that opted into metadata.
     *
     * @param string $collection
     *
     * @return Form|FormBuilder|null
     */
    public function getMediaItemForm(string $collection): Form|FormBuilder|null
    {
        return $this->getTypeParam('media-item-forms', [])[$collection] ?? null;
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
