<?php

namespace JobMetric\Post\Support;

use Closure;
use InvalidArgumentException;

/** Fluent capabilities for one post type. */
class PostTypeBuilder
{
    public function __construct(private readonly string $type, private readonly PostTypeRegistry $registry) {}

    public function label(string $label): static { return $this->option('label', $label); }
    public function description(string $description): static { return $this->option('description', $description); }
    public function translationFields(array $fields): static
    {
        $fields = array_values(array_unique(array_map('strval', $fields)));
        if ($fields === []) { throw new InvalidArgumentException('A post type must allow at least one translated field.'); }
        return $this->option('translation-fields', $fields);
    }
    public function allowTaxonomy(string $type, string $collection = 'taxonomies', bool $multiple = true): static
    {
        $taxonomies = (array) $this->registry->getOption($this->type, 'taxonomy-types', []);
        $taxonomies[$collection] = ['type' => $type, 'multiple' => $multiple];
        return $this->option('taxonomy-types', $taxonomies);
    }
    public function taxonomyTypes(array $types): static { return $this->option('taxonomy-types', $types); }
    public function mediaCollection(string $name, bool $multiple = false, array $mimeTypes = ['image']): static
    {
        $collections = (array) $this->registry->getOption($this->type, 'media-collections', []);
        $collections[$name] = ['multiple' => $multiple, 'mimeTypes' => $mimeTypes];
        return $this->option('media-collections', $collections);
    }
    public function metadataFields(array $fields): static { return $this->option('metadata-fields', array_values(array_unique(array_map('strval', $fields)))); }
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
    public function urlPrefix(string $prefix): static { return $this->option('url-prefix', trim($prefix, '/')); }
    public function comments(bool $enabled = true): static { return $this->option('comments', $enabled); }
    public function hierarchical(): static { return $this->option('hierarchical', true); }
    public function workflow(string $flow): static { return $this->option('workflow', $flow); }
    public function option(string $key, mixed $value): static
    {
        $this->registry->setOption($this->type, $key, $value);
        return $this;
    }
    public function get(): array { return $this->registry->get($this->type); }
}
