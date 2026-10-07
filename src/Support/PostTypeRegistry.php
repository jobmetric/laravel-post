<?php

namespace JobMetric\Post\Support;

use Illuminate\Support\Arr;
use InvalidArgumentException;

/** Register post type capabilities through a fluent builder. */
class PostTypeRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $types = [];

    public function register(string $type, array $options = []): PostTypeBuilder
    {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $type)) {
            throw new InvalidArgumentException('Post type keys must be lowercase identifiers.');
        }

        $this->types[$type] ??= ['label' => $type, 'translation-fields' => ['title', 'excerpt', 'content'], 'taxonomy-types' => [], 'media-collections' => []];
        $builder = new PostTypeBuilder($type, $this);

        foreach ($options as $key => $value) {
            $builder->option((string) $key, $value);
        }

        return $builder;
    }

    public function for(string $type): PostTypeBuilder
    {
        $this->ensureExists($type);

        return new PostTypeBuilder($type, $this);
    }

    public function has(string $type): bool { return array_key_exists($type, $this->types); }
    public function values(): array { return array_keys($this->types); }
    public function all(): array { return $this->types; }
    public function get(string $type): array { $this->ensureExists($type); return $this->types[$type]; }
    public function getOption(string $type, string $key, mixed $default = null): mixed { return Arr::get($this->get($type), $key, $default); }
    public function setOption(string $type, string $key, mixed $value): void { $this->ensureExists($type); Arr::set($this->types[$type], $key, $value); }
    public function ensureExists(string $type): void
    {
        if (! $this->has($type)) {
            throw new InvalidArgumentException("Post type [{$type}] is not registered.");
        }
    }
}
