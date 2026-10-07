<?php

namespace JobMetric\Post\Facades;

use JobMetric\Post\Support\PostTypeBuilder;
use JobMetric\Post\Support\PostTypeRegistry as Registry;
use Illuminate\Support\Facades\Facade;

/**
 * Provide a concise extension API for registering post types.
 *
 * @method static PostTypeBuilder register(string $type, array $options = [])
 * @method static PostTypeBuilder for(string $type)
 * @method static bool has(string $type)
 * @method static array values()
 * @method static array all()
 * @method static array get(string $type)
 * @method static mixed getOption(string $type, string $key, mixed $default = null)
 *
 * @see Registry
 */
class PostTypeRegistry extends Facade
{
    /**
     * Resolve the post type registry from Laravel's service container.
     *
     * @return class-string<Registry>
     */
    protected static function getFacadeAccessor(): string
    {
        return Registry::class;
    }
}
