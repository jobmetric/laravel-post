<?php

namespace JobMetric\Post\Support;

use Illuminate\Support\Arr;
use InvalidArgumentException;

/** Register post type capabilities through a fluent builder. */
class PostTypeRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $types = [];

    /**
     * Register or extend a post type, rolling back invalid configuration.
     *
     * @param string $type
     * @param array<string, mixed> $options
     *
     * @return PostTypeBuilder
     * @throws \InvalidArgumentException
     */
    public function register(string $type, array $options = []): PostTypeBuilder
    {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $type)) {
            throw new InvalidArgumentException('Post type keys must be lowercase identifiers.');
        }

        $isNew = ! $this->has($type);
        $previous = $this->types[$type] ?? null;
        $this->types[$type] ??= ['label' => $type, 'translation-fields' => ['title', 'excerpt', 'content'], 'taxonomy-types' => [], 'media-collections' => []];
        $builder = new PostTypeBuilder($type, $this);

        try {
            return $builder->apply($options);
        } catch (\Throwable $exception) {
            if ($isNew) {
                unset($this->types[$type]);
            } else {
                $this->types[$type] = $previous;
            }

            throw $exception;
        }
    }

    /**
     * Create a fluent builder for an existing post type.
     *
     * @param string $type
     *
     * @return PostTypeBuilder
     */
    public function for(string $type): PostTypeBuilder
    {
        $this->ensureExists($type);

        return new PostTypeBuilder($type, $this);
    }

    /**
     * Determine whether a post type has been registered.
     *
     * @param string $type
     *
     * @return bool
     */
    public function has(string $type): bool
    {
        return array_key_exists($type, $this->types);
    }
    /**
     * Return the registered post type identifiers.
     *
     * @return list<string>
     */
    public function values(): array
    {
        return array_keys($this->types);
    }
    /**
     * Return all registered type definitions.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->types;
    }
    /**
     * Return one registered post type definition.
     *
     * @param string $type
     *
     * @return array<string, mixed>
     */
    public function get(string $type): array
    {
        $this->ensureExists($type);
        return $this->types[$type];
    }
    /**
     * Read a nested option from a registered post type.
     *
     * @param string $type
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getOption(string $type, string $key, mixed $default = null): mixed
    {
        return Arr::get($this->get($type), $key, $default);
    }
    /**
     * Set a nested option on a registered post type.
     *
     * @param string $type
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function setOption(string $type, string $key, mixed $value): void
    {
        $this->ensureExists($type);
        Arr::set($this->types[$type], $key, $value);
    }
    /**
     * Reject access to an unregistered post type.
     *
     * @param string $type
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function ensureExists(string $type): void
    {
        if (! $this->has($type)) {
            throw new InvalidArgumentException("Post type [{$type}] is not registered.");
        }
    }

    /**
     * Remove a registered type without touching persisted posts.
     *
     * @param string $type
     *
     * @return static
     */
    public function unregister(string $type): static
    {
        unset($this->types[$type]);

        return $this;
    }

    /**
     * Clear in-memory definitions for isolated application lifecycles.
     *
     * @return static
     */
    public function clear(): static
    {
        $this->types = [];

        return $this;
    }
}
