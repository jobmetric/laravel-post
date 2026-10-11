<?php

namespace JobMetric\Post\Facades;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Facade;
use JobMetric\PackageCore\Output\Response;
use JobMetric\Post\Models\Post as PostModel;
use JobMetric\Post\Post as PostService;

/**
 * Access post persistence through the package service container binding.
 *
 * @method static Builder query(string $type, array $with = [])
 * @method static Response store(string $type, array $data, Authenticatable|null $actor = null)
 * @method static Response update(string $type, int $id, array $data, Authenticatable|null $actor = null)
 * @method static Response delete(string $type, int $id)
 * @method static void syncMedia(PostModel $post, array $collections)
 * @method static void syncTaxonomies(PostModel $post, array $collections)
 *
 * @see PostService
 */
class Post extends Facade
{
    /**
     * Resolve the reusable post service.
     *
     * @return class-string<PostService>
     */
    protected static function getFacadeAccessor(): string
    {
        return PostService::class;
    }
}
