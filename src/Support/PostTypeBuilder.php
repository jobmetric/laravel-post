<?php

namespace JobMetric\Post\Support;

use Closure;
use InvalidArgumentException;

/** Fluent capabilities for one post type. */
class PostTypeBuilder
{
    private const SEO_TRANSLATION_FIELDS = ['meta_title', 'meta_description', 'meta_keywords'];

    public function __construct(private readonly string $type, private readonly PostTypeRegistry $registry) {}

    /**
     * Apply registered post type capabilities using their fluent API.
     *
     * @param array<string, mixed> $options
     *
     * @return static
     */
    public function apply(array $options): static
    {
        foreach ($options as $key => $value) {
            if ($key === 'label') {
                if (! is_string($value)) throw new InvalidArgumentException('The post type label must be a string.');
                $this->label($value);
            } elseif ($key === 'description') {
                if (! is_string($value)) throw new InvalidArgumentException('The post type description must be a string.');
                $this->description($value);
            } elseif ($key === 'view-permission') {
                if (! is_string($value)) throw new InvalidArgumentException('Post view permission must be a string.');
                $this->viewPermission($value);
            } elseif ($key === 'manage-permission') {
                if (! is_string($value)) throw new InvalidArgumentException('Post manage permission must be a string.');
                $this->managePermission($value);
            } elseif ($key === 'translation-fields') {
                if (! is_array($value)) throw new InvalidArgumentException('Post translation fields must be an array.');
                $this->translationFields($value);
            } elseif ($key === 'has-seo') {
                if (! is_bool($value)) throw new InvalidArgumentException('The post SEO option must be boolean.');
                $this->hasSeo($value);
            } elseif ($key === 'taxonomy-types') {
                if (! is_array($value)) throw new InvalidArgumentException('Post taxonomy types must be an array.');
                $this->taxonomyTypes($value);
            } elseif ($key === 'media-collections') {
                if (! is_array($value)) throw new InvalidArgumentException('Post media collections must be an array.');
                foreach ($value as $name => $definition) {
                    if (! is_array($definition)) throw new InvalidArgumentException('A post media collection definition must be an array.');
                    $this->mediaCollection($name, (bool) ($definition['multiple'] ?? false), (array) ($definition['mimeTypes'] ?? ['image']));
                }
            } elseif ($key === 'metadata-fields') {
                if (! is_array($value)) throw new InvalidArgumentException('Post metadata fields must be an array.');
                $this->metadataFields($value);
            } elseif ($key === 'custom-fields') {
                if (! is_array($value)) throw new InvalidArgumentException('Post custom fields must be an array.');
                foreach ($value as $field => $definition) {
                    $this->customField($field, $definition);
                }
            } elseif ($key === 'url-prefix') {
                if (! is_string($value)) throw new InvalidArgumentException('The post URL prefix must be a string.');
                $this->urlPrefix($value);
            } elseif ($key === 'has-url') {
                if (! is_bool($value)) throw new InvalidArgumentException('The post URL option must be boolean.');
                if ($value) $this->url();
                else $this->option('has-url', false);
            } elseif ($key === 'comments') {
                if (! is_bool($value)) throw new InvalidArgumentException('The post comments option must be boolean.');
                $this->comments($value);
            } elseif ($key === 'hierarchical') {
                if (! is_bool($value)) throw new InvalidArgumentException('The post hierarchical option must be boolean.');
                $value ? $this->hierarchical() : $this->option($key, false);
            } elseif ($key === 'workflow') {
                if (! is_string($value)) throw new InvalidArgumentException('The post workflow must be a string.');
                $this->workflow($value);
            } elseif ($key === 'filters') {
                if (! is_array($value)) throw new InvalidArgumentException('Post filters must be an array.');
                foreach ($value as $filter => $definition) {
                    $this->addFilter($filter, $definition);
                }
            } else {
                $this->option((string) $key, $value);
            }
        }

        return $this;
    }

    public function label(string $label): static { return $this->option('label', $label); }
    public function getLabel(): string { return trans($this->registry->getOption($this->type, 'label', '')); }
    public function description(string $description): static { return $this->option('description', $description); }
    public function getDescription(): string { return trans($this->registry->getOption($this->type, 'description', '')); }
    public function url(): static { return $this->option('has-url', true); }
    public function hasUrl(): bool
    {
        return (bool) $this->registry->getOption(
            $this->type,
            'has-url',
            array_key_exists('url-prefix', $this->registry->get($this->type))
        );
    }
    public function getUrlPrefix(string $default = ''): string { return (string) $this->registry->getOption($this->type, 'url-prefix', $default); }
    public function viewPermission(string $permission): static
    {
        $this->assertPermission($permission);

        return $this->option('view-permission', $permission);
    }
    public function getViewPermission(): ?string { return $this->registry->getOption($this->type, 'view-permission'); }
    public function managePermission(string $permission): static
    {
        $this->assertPermission($permission);

        return $this->option('manage-permission', $permission);
    }
    public function getManagePermission(): ?string { return $this->registry->getOption($this->type, 'manage-permission'); }
    public function translationFields(array $fields): static
    {
        $fields = array_values(array_unique(array_map('strval', $fields)));
        if ($this->registry->getOption($this->type, 'has-seo', false)) {
            $fields = array_values(array_unique([...$fields, ...self::SEO_TRANSLATION_FIELDS]));
        }
        if ($fields === []) { throw new InvalidArgumentException('A post type must allow at least one translated field.'); }
        return $this->option('translation-fields', $fields);
    }
    public function getTranslationFields(): array { return (array) $this->registry->getOption($this->type, 'translation-fields', ['title', 'excerpt', 'content']); }
    public function hasSeo(bool $enabled = true): static
    {
        $this->option('has-seo', $enabled);
        $fields = (array) $this->registry->getOption($this->type, 'translation-fields', ['title', 'excerpt', 'content']);
        $fields = $enabled
            ? [...$fields, ...self::SEO_TRANSLATION_FIELDS]
            : array_values(array_diff($fields, self::SEO_TRANSLATION_FIELDS));

        return $this->option('translation-fields', array_values(array_unique($fields)));
    }
    public function isSeoEnabled(): bool { return (bool) $this->registry->getOption($this->type, 'has-seo', false); }
    public function allowTaxonomy(string $type, string $collection = 'taxonomies', bool $multiple = true): static
    {
        $taxonomies = (array) $this->registry->getOption($this->type, 'taxonomy-types', []);
        $taxonomies[$collection] = ['type' => $type, 'multiple' => $multiple];
        return $this->option('taxonomy-types', $taxonomies);
    }
    public function getTaxonomyTypes(): array { return (array) $this->registry->getOption($this->type, 'taxonomy-types', []); }
    public function filterTaxonomy(string $collection, bool $enabled = true): static
    {
        $taxonomies = (array) $this->registry->getOption($this->type, 'taxonomy-types', []);
        if (! isset($taxonomies[$collection])) {
            throw new InvalidArgumentException("Taxonomy collection [{$collection}] is not registered for post type [{$this->type}].");
        }
        $taxonomies[$collection]['filter'] = $enabled;
        return $this->option('taxonomy-types', $taxonomies);
    }
    public function addFilter(string $key, array $definition): static
    {
        if ($key === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException('Filter keys must contain lowercase letters, digits, and underscores.');
        }
        if (! is_string($definition['label'] ?? null) || ! is_array($definition['options'] ?? null) || ! is_callable($definition['apply'] ?? null)) {
            throw new InvalidArgumentException('A post filter requires a label, options array, and apply callback.');
        }
        $filters = (array) $this->registry->getOption($this->type, 'filters', []);
        $filters[$key] = $definition;
        return $this->option('filters', $filters);
    }
    public function getFilters(): array { return (array) $this->registry->getOption($this->type, 'filters', []); }
    public function taxonomyTypes(array $types): static { return $this->option('taxonomy-types', $types); }
    public function mediaCollection(string $name, bool $multiple = false, array $mimeTypes = ['image']): static
    {
        $collections = (array) $this->registry->getOption($this->type, 'media-collections', []);
        $collections[$name] = ['multiple' => $multiple, 'mimeTypes' => $mimeTypes];
        return $this->option('media-collections', $collections);
    }
    public function getMediaCollections(): array { return (array) $this->registry->getOption($this->type, 'media-collections', []); }
    public function metadataFields(array $fields): static { return $this->option('metadata-fields', array_values(array_unique(array_map('strval', $fields)))); }
    public function getMetadataFields(): array { return (array) $this->registry->getOption($this->type, 'metadata-fields', []); }
    public function customField(string $key, array $definition): static
    {
        $fields = (array) $this->registry->getOption($this->type, 'custom-fields', []);
        $fields[$key] = $definition;
        $metadataFields = (array) $this->registry->getOption($this->type, 'metadata-fields', []);
        if (! in_array('*', $metadataFields, true)) {
            $metadataFields[] = $key;
            $this->option('metadata-fields', array_values(array_unique($metadataFields)));
        }
        return $this->option('custom-fields', $fields);
    }
    public function getCustomFields(): array { return (array) $this->registry->getOption($this->type, 'custom-fields', []); }
    public function urlPrefix(string $prefix): static
    {
        $prefix = trim($prefix, '/');
        if ($prefix !== '' && ! preg_match('~^[\pL\pN_-]+(?:/[\pL\pN_-]+)*$~u', $prefix)) {
            throw new InvalidArgumentException('The URL prefix must contain path segments only.');
        }

        return $this->option('url-prefix', $prefix);
    }
    public function comments(bool $enabled = true): static { return $this->option('comments', $enabled); }
    public function hasComments(): bool { return (bool) $this->registry->getOption($this->type, 'comments', false); }
    public function hierarchical(): static { return $this->option('hierarchical', true); }
    public function workflow(string $flow): static { return $this->option('workflow', $flow); }
    protected function option(string $key, mixed $value): static
    {
        $this->registry->setOption($this->type, $key, $value);
        return $this;
    }
    public function get(): array { return $this->registry->get($this->type); }

    /**
     * Validate the permission key used to protect post administration.
     *
     * @param string $permission
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertPermission(string $permission): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/', $permission)) {
            throw new InvalidArgumentException('Invalid post administration permission.');
        }
    }
}
