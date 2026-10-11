<?php

namespace JobMetric\Post\Support;

use InvalidArgumentException;

/**
 * Configure named taxonomy collections and their selection policies.
 */
trait HasPostTaxonomyOptions
{
    /**
     * Allow a registered taxonomy type in a named post collection.
     *
     * @param string $type
     * @param string $collection
     * @param bool $multiple
     *
     * @return static
     */
    public function taxonomy(string $type, string $collection = 'taxonomies', bool $multiple = true): static
    {
        $this->assertFieldName($type);
        $this->assertFieldName($collection);
        $taxonomies = (array) $this->getTypeParam('taxonomy-types', []);
        $taxonomies[$collection] = ['type' => $type, 'multiple' => $multiple];
        return $this->option('taxonomy-types', $taxonomies);
    }

    /**
     * Return the taxonomy type and selection policy for each collection.
     *
     * @return array<array-key, mixed>
     */
    public function getTaxonomyTypes(): array
    {
        return (array) $this->getTypeParam('taxonomy-types', []);
    }

    /**
     * Set all named taxonomy collections for this post type.
     *
     * @param array<string, mixed> $types
     *
     * @return static
     */
    public function taxonomyTypes(array $types): static
    {
        foreach ($types as $collection => $definition) {
            $this->assertFieldName($collection);
            if (! is_array($definition) || ! is_string($definition['type'] ?? null)
                || (isset($definition['multiple']) && ! is_bool($definition['multiple']))) {
                throw new InvalidArgumentException('Taxonomy collections require a type and boolean selection policy.');
            }
            $this->assertFieldName($definition['type']);
        }

        return $this->option('taxonomy-types', $types);
    }
}
