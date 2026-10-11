<?php

namespace JobMetric\Post\Support;

use InvalidArgumentException;

/**
 * Configure built-in and extensible post list filters.
 */
trait HasPostFilterOptions
{
    /**
     * Add built-in field filters and custom filter definitions to the post list.
     *
     * @param array<array-key, mixed> $filters
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function filters(array $filters): static
    {
        $fields = (array) $this->getTypeParam('filter-fields', []);
        foreach ($filters as $key => $filter) {
            if (is_string($key) && is_array($filter) && isset($filter['label'], $filter['options'], $filter['apply'])) {
                $this->addFilter($key, $filter);
                continue;
            }
            if (is_string($key) && $filter === true) {
                $filter = $key;
            }
            if (! is_string($filter) || ! preg_match('/^(status|taxonomy\.[a-z][a-z0-9_-]*|metadata\.[a-z][a-z0-9_-]*)$/', $filter)) {
                throw new InvalidArgumentException('Post filter fields must be status, taxonomy types, or metadata keys.');
            }
            $fields[] = $filter;
        }
        return $this->option('filter-fields', array_values(array_unique($fields)));
    }

    /**
     * Return the configured built-in list filter identifiers.
     *
     * @return array<array-key, mixed>
     */
    public function getFilterFields(): array
    {
        return (array) $this->getTypeParam('filter-fields', []);
    }

    /**
     * Register a named list filter with its options and query callback.
     *
     * @param string $key
     * @param array<string, mixed> $definition
     *
     * @return static
     * @throws InvalidArgumentException
     */
    public function addFilter(string $key, array $definition): static
    {
        if ($key === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException('Filter keys must contain lowercase letters, digits, and underscores.');
        }
        if (! is_string($definition['label'] ?? null) || ! is_array($definition['options'] ?? null) || ! is_callable($definition['apply'] ?? null)) {
            throw new InvalidArgumentException('A post filter requires a label, options array, and apply callback.');
        }
        $filters = (array) $this->getTypeParam('filters', []);
        $filters[$key] = $definition;
        return $this->option('filters', $filters);
    }

    /**
     * Return the custom list filters keyed by identifier.
     *
     * @return array<array-key, mixed>
     */
    public function getFilters(): array
    {
        return (array) $this->getTypeParam('filters', []);
    }
}
