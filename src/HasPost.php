<?php

namespace JobMetric\Post;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use JobMetric\Post\Models\Post;

/** Attach posts to any model through named post collections. */
trait HasPost
{
    /** @var array<string, array{multiple?: bool}> */
    protected array $postCollections = ['default' => ['multiple' => true]];

    /**
     * Relate posts through named polymorphic collections.
     *
     * @return MorphToMany
     */
    public function posts(): MorphToMany
    {
        return $this->morphToMany(Post::class, 'postable', config('post.tables.post_relation', 'post_relations'))
            ->withPivot('collection')->withTimestamps('created_at', false);
    }

    /**
     * Restrict related posts to one allowed collection.
     *
     * @param string $collection
     *
     * @return MorphToMany
     */
    public function postsIn(string $collection = 'default'): MorphToMany
    {
        $this->assertPostCollection($collection);
        return $this->posts()->wherePivot('collection', $collection);
    }

    /**
     * Attach a post while enforcing the named collection cardinality.
     *
     * @param int|Post $post
     * @param string $collection
     *
     * @return void
     * @throws \Throwable
     */
    public function attachPost(int|Post $post, string $collection = 'default'): void
    {
        $this->assertPostCollection($collection);
        $id = $post instanceof Post ? $post->getKey() : $post;
        Post::query()->findOrFail($id);
        $this->getConnection()->transaction(function () use ($id, $collection): void {
            if (! ($this->postCollections[$collection]['multiple'] ?? true)) {
                $this->postsIn($collection)->detach();
            }
            $this->postsIn($collection)->syncWithoutDetaching([$id => ['collection' => $collection]]);
        });
    }

    /**
     * Replace a named post collection without affecting other collections.
     *
     * @param string $collection
     * @param list<int> $postIds
     *
     * @return void
     * @throws \Throwable
     */
    public function syncPosts(string $collection, array $postIds): void
    {
        $this->assertPostCollection($collection);
        if (! ($this->postCollections[$collection]['multiple'] ?? true) && count(array_unique($postIds)) > 1) {
            throw new \InvalidArgumentException("Post collection [{$collection}] accepts one post only.");
        }
        $this->getConnection()->transaction(function () use ($collection, $postIds): void {
            $this->postsIn($collection)->detach();
            foreach (array_unique(array_map('intval', $postIds)) as $postId) {
                $this->attachPost($postId, $collection);
            }
        });
    }

    /**
     * Reject collection names that the owning model has not declared.
     *
     * @param string $collection
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    private function assertPostCollection(string $collection): void
    {
        if (! array_key_exists($collection, $this->postCollections)) {
            throw new \InvalidArgumentException("Post collection [{$collection}] is not allowed for ".static::class.'.');
        }
    }
}
