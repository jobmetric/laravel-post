<?php

namespace JobMetric\Post\Facades;

use Illuminate\Support\Facades\Facade;

/** @method static \JobMetric\Post\Support\PostTypeBuilder register(string $type, array $options = [])
 * @method static \JobMetric\Post\Support\PostTypeBuilder for(string $type)
 * @method static bool has(string $type)
 * @method static array values()
 * @method static array all()
 * @method static array get(string $type)
 * @method static mixed getOption(string $type, string $key, mixed $default = null)
 * @see \JobMetric\Post\Support\PostTypeRegistry
 */
class PostTypeRegistry extends Facade
{
    protected static function getFacadeAccessor(): string { return \JobMetric\Post\Support\PostTypeRegistry::class; }
}
