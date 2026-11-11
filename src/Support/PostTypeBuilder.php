<?php

namespace JobMetric\Post\Support;

use InvalidArgumentException;

/**
 * Build one post type from reusable content and administration capabilities.
 */
class PostTypeBuilder
{
    use HasPostAdministrationOptions;
    use HasPostContentOptions;
    use HasPostFilterOptions;
    use HasPostMediaOptions;
    use HasPostTaxonomyOptions;
    use HasPostLifecycleOptions;

    private const SEO_TRANSLATION_FIELDS = ['meta_title', 'meta_description', 'meta_keywords'];

    /**
     * Connect the builder to its registered post type.
     *
     * @param string $type
     * @param PostTypeRegistry $registry
     *
     * @return void
     */
    public function __construct(
        protected readonly string $type,
        protected readonly PostTypeRegistry $registry
    ) {
    }

    /**
     * Apply array configuration through the same validation as fluent chains.
     *
     * @param array<string, mixed> $options
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function apply(array $options): static
    {
        $chains = [
            'label' => ['label', 'string'],
            'description' => ['description', 'string'],
            'permission' => ['permission', 'string'],
            'view-permission' => ['viewPermission', 'string'],
            'manage-permission' => ['managePermission', 'string'],
            'translation-fields' => ['translationFields', 'array'],
            'metadata-fields' => ['metadataFields', 'array'],
            'has-seo' => ['hasSeo', 'boolean'],
            'seo' => ['seo', 'boolean'],
            'has-password' => ['hasPassword', 'boolean'],
            'password' => ['password', 'boolean'],
            'taxonomy-types' => ['taxonomyTypes', 'array'],
            'url' => ['url', 'string'],
            'comments' => ['comments', 'boolean'],
            'workflow' => ['workflow', 'string'],
            'filters' => ['filters', 'array'],
            'optimistic-locking' => ['optimisticLocking', 'boolean'],
            'field-ownership' => ['fieldOwnership', 'array'],
            'back-url' => ['backUrl', 'string'],
        ];

        foreach ($options as $key => $value) {
            if (isset($chains[$key])) {
                [$method, $expected] = $chains[$key];
                if (gettype($value) !== $expected) {
                    throw new InvalidArgumentException("Invalid value for post option [{$key}]; expected {$expected}.");
                }
                $this->{$method}($value);
                continue;
            }

            if ($key === 'media-collections' || $key === 'custom-fields') {
                if (! is_array($value)) {
                    throw new InvalidArgumentException("Post option [{$key}] must be an array.");
                }
                foreach ($value as $name => $definition) {
                    if (! is_string($name) || ! is_array($definition)) {
                        throw new InvalidArgumentException("Post option [{$key}] requires named array definitions.");
                    }
                    if ($key === 'custom-fields') {
                        $this->customField($name, $definition);
                    } else {
                        if (isset($definition['multiple']) && ! is_bool($definition['multiple'])) {
                            throw new InvalidArgumentException('Media selection cardinality must be boolean.');
                        }
                        $this->mediaCollection($name, $definition['multiple'] ?? false, $definition['mimeTypes'] ?? ['image']);
                    }
                }
                continue;
            }

            if (in_array($key, ['help', 'base-media', 'hierarchical', 'change-status-in-list'], true)) {
                if (! is_bool($value)) {
                    throw new InvalidArgumentException("Post option [{$key}] must be boolean.");
                }
                if ($key === 'help') {
                    $this->option('has-help', $value);
                } elseif ($key === 'base-media') {
                    if ($value) {
                        $this->baseMedia();
                    } else {
                        $collections = $this->getMediaCollections();
                        unset($collections['base']);
                        $this->option('media-collections', $collections);
                    }
                } else {
                    $this->option($key, $value);
                }
                continue;
            }

            $this->option((string) $key, $value);
        }

        return $this;
    }

    /**
     * Return the complete configuration for this post type.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->registry->get($this->type);
    }

    /**
     * Persist a type option while retaining fluent chaining.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return static
     */
    protected function option(string $key, mixed $value): static
    {
        $this->setTypeParam($key, $value);

        return $this;
    }

    /**
     * Set a type parameter through the owning registry.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    protected function setTypeParam(string $key, mixed $value): void
    {
        $this->registry->setOption($this->type, $key, $value);
    }

    /**
     * Read a type parameter through the owning registry.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    protected function getTypeParam(string $key, mixed $default = null): mixed
    {
        return $this->registry->getOption($this->type, $key, $default);
    }

    /**
     * Reject unsafe field and collection identifiers at registration time.
     *
     * @param mixed $name
     * @param bool $allowWildcard
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function assertFieldName(mixed $name, bool $allowWildcard = false): void
    {
        if ($allowWildcard && $name === '*') {
            return;
        }
        if (! is_string($name) || ! preg_match('/^[a-z][a-z0-9_-]*$/', $name)) {
            throw new InvalidArgumentException('Field and collection keys must be lowercase identifiers.');
        }
    }
}
