<?php

namespace JobMetric\Post;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use JobMetric\Post\Models\Post;

/** Attach posts to any model through named post collections. */
trait HasPost
{
    /** @var array<string, array{multiple?: bool}> */
    protected array $postCollections = ['default' => ['multiple' => true]];

    public function posts(): MorphToMany
    {
        return $this->morphToMany(Post::class, 'postable', config('post.tables.post_relation', 'post_relations'))
            ->withPivot('collection')->withTimestamps('created_at', false);
    }

    public function postsIn(string $collection = 'default'): MorphToMany
    {
        $this->assertPostCollection($collection);
        return $this->posts()->wherePivot('collection', $collection);
    }

    public function attachPost(int|Post $post, string $collection = 'default'): void
    {
        $this->assertPostCollection($collection);
        $id = $post instanceof Post ? $post->getKey() : $post;
        if (! ($this->postCollections[$collection]['multiple'] ?? true)) {
            $this->posts()->wherePivot('collection', $collection)->detach();
        }
        $this->posts()->syncWithoutDetaching([$id => ['collection' => $collection]]);
    }

    public function syncPosts(string $collection, array $postIds): void
    {
        $this->assertPostCollection($collection);
        if (! ($this->postCollections[$collection]['multiple'] ?? true) && count(array_unique($postIds)) > 1) {
            throw new \InvalidArgumentException("Post collection [{$collection}] accepts one post only.");
        }
        $this->posts()->newPivotStatement()
            ->where('postable_type', $this->getMorphClass())->where('postable_id', $this->getKey())->where('collection', $collection)->delete();
        foreach (array_unique(array_map('intval', $postIds)) as $postId) { $this->attachPost($postId, $collection); }
    }

    private function assertPostCollection(string $collection): void
    {
        if (! array_key_exists($collection, $this->postCollections)) {
            throw new \InvalidArgumentException("Post collection [{$collection}] is not allowed for ".static::class.'.');
        }
    }
}
