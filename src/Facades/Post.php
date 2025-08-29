<?php

namespace JobMetric\Post\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JobMetric\Post\Post
 *
 * @method static \Spatie\QueryBuilder\QueryBuilder query(string $type, array $filter = [], array $with = [])
 * @method static \Illuminate\Http\Resources\Json\AnonymousResourceCollection paginate(string $type, array $filter = [], int $page_limit = 15, array $with = [])
 * @method static \Illuminate\Http\Resources\Json\AnonymousResourceCollection all(string $type, array $filter = [], array $with = [])
 * @method static array store(array $data)
 * @method static array update(int $post_id, array $data)
 * @method static array delete(int $post_id)
 * @method static string getName(int $post_id, bool $concat = true, string $locale = null)
 * @method static array usedIn(int $post_id)
 * @method static bool hasUsed(int $post_id)
 */
class Post extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \JobMetric\Post\Post::class;
    }
}
