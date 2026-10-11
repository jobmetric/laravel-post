<?php

namespace JobMetric\Post\Support;

use InvalidArgumentException;

/**
 * Configure translated content, SEO, metadata, URLs and workflow capabilities.
 */
trait HasPostContentOptions
{
    /**
     * Register translated field names while retaining enabled SEO fields.
     *
     * @param array<array-key, mixed> $fields
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function translationFields(array $fields): static
    {
        foreach ($fields as $field) {
            $this->assertFieldName($field, true);
        }
        $fields = array_values(array_unique(array_map('strval', $fields)));
        if ($this->getTypeParam('has-seo', false)) {
            $fields = array_values(array_unique([...$fields, ...self::SEO_TRANSLATION_FIELDS]));
        }
        if ($fields === []) {
            throw new InvalidArgumentException('A post type must allow at least one translated field.');
        }
        return $this->option('translation-fields', $fields);
    }

    /**
     * Return the translated fields allowed for this post type.
     *
     * @return array<array-key, mixed>
     */
    public function getTranslationFields(): array
    {
        return (array) $this->getTypeParam('translation-fields', ['title', 'excerpt', 'content']);
    }

    /**
     * Enable or remove translated SEO fields, index metadata and the sharing image.
     *
     * @param bool $enabled
     *
     * @return static
     */
    public function hasSeo(bool $enabled = true): static
    {
        $this->option('has-seo', $enabled);
        $fields = (array) $this->getTypeParam('translation-fields', ['title', 'excerpt', 'content']);
        $fields = $enabled
            ? [...$fields, ...self::SEO_TRANSLATION_FIELDS]
            : array_values(array_diff($fields, self::SEO_TRANSLATION_FIELDS));
        $this->option('translation-fields', array_values(array_unique($fields)));

        if ($enabled) {
            $this->mediaCollection('seo_image', false, ['image']);
            $this->customField('seo_index', [
                'label' => 'post::base.seo_index',
                'type' => 'select',
                'rules' => ['sometimes', 'boolean'],
            ]);
        } else {
            $media = $this->getMediaCollections();
            unset($media['seo_image']);
            $this->option('media-collections', $media);
            $customFields = $this->getCustomFields();
            unset($customFields['seo_index']);
            $this->option('custom-fields', $customFields);
            $this->metadataFields(array_values(array_diff($this->getMetadataFields(), ['seo_index'])));
        }

        return $this;
    }

    /**
     * Configure the same SEO capabilities through the concise fluent alias.
     *
     * @param bool $enabled
     *
     * @return static
     */
    public function seo(bool $enabled = true): static
    {
        return $this->hasSeo($enabled);
    }

    /**
     * Determine whether SEO capabilities are enabled.
     *
     * @return bool
     */
    public function isSeoEnabled(): bool
    {
        return (bool) $this->getTypeParam('has-seo', false);
    }

    /**
     * Register the metadata keys allowed for this post type.
     *
     * @param array<array-key, mixed> $fields
     *
     * @return static
     */
    public function metadataFields(array $fields): static
    {
        foreach ($fields as $field) {
            $this->assertFieldName($field, true);
        }
        if ($this->isSeoEnabled()) {
            $fields[] = 'seo_index';
        }

        return $this->option('metadata-fields', array_values(array_unique($fields)));
    }

    /**
     * Return the allowed metadata keys.
     *
     * @return array<array-key, mixed>
     */
    public function getMetadataFields(): array
    {
        return (array) $this->getTypeParam('metadata-fields', []);
    }

    /**
     * Register a metadata field definition and include its key in the allowlist.
     *
     * @param string $key
     * @param array<string, mixed> $definition
     *
     * @return static
     */
    public function customField(string $key, array $definition): static
    {
        $this->assertFieldName($key);
        $fields = (array) $this->getTypeParam('custom-fields', []);
        $fields[$key] = $definition;
        $metadataFields = (array) $this->getTypeParam('metadata-fields', []);
        if (! in_array('*', $metadataFields, true)) {
            $metadataFields[] = $key;
            $this->option('metadata-fields', array_values(array_unique($metadataFields)));
        }
        return $this->option('custom-fields', $fields);
    }

    /**
     * Return the registered metadata field definitions.
     *
     * @return array<array-key, mixed>
     */
    public function getCustomFields(): array
    {
        return (array) $this->getTypeParam('custom-fields', []);
    }

    /**
     * Enable URL support with a validated website path prefix.
     *
     * @param string $prefix
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function url(string $prefix = ''): static
    {
        $prefix = trim($prefix, '/');
        if ($prefix !== '' && ! preg_match('~^[\pL\pN_-]+(?:/[\pL\pN_-]+)*$~u', $prefix)) {
            throw new InvalidArgumentException('The URL prefix must contain path segments only.');
        }

        $this->option('has-url', true);

        return $this->option('url-prefix', $prefix);
    }

    /**
     * Determine whether this post type supports website URLs.
     *
     * @return bool
     */
    public function hasUrl(): bool
    {
        return (bool) $this->registry->getOption(
            $this->type,
            'has-url',
            array_key_exists('url-prefix', $this->registry->get($this->type))
        );
    }

    /**
     * Return the configured website path prefix.
     *
     * @param string $default
     *
     * @return string
     */
    public function getUrlPrefix(string $default = ''): string
    {
        return (string) $this->getTypeParam('url-prefix', $default);
    }

    /**
     * Configure whether this post type supports comments.
     *
     * @param bool $enabled
     *
     * @return static
     */
    public function comments(bool $enabled = true): static
    {
        return $this->option('comments', $enabled);
    }

    /**
     * Determine whether comments are enabled.
     *
     * @return bool
     */
    public function hasComments(): bool
    {
        return (bool) $this->getTypeParam('comments', false);
    }

    /**
     * Record the hierarchy capability for clients that support it.
     *
     * @return static
     */
    public function hierarchical(): static
    {
        return $this->option('hierarchical', true);
    }

    /**
     * Bind this post type to a named workflow collection.
     *
     * @param string $flow
     *
     * @return static
     */
    public function workflow(string $flow): static
    {
        return $this->option('workflow', $flow);
    }
}
