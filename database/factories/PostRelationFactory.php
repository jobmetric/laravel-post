<?php

namespace JobMetric\Post\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Post\Models\PostRelation;

/**
 * @extends Factory<PostRelation>
 */
class PostRelationFactory extends Factory
{
    protected $model = PostRelation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => null,
            'postable_type' => null,
            'postable_id' => null,
            'collection' => null,
        ];
    }

    /**
     * set post id
     *
     * @param int $post_id
     *
     * @return static
     */
    public function setPostId(int $post_id): static
    {
        return $this->state(fn(array $attributes) => [
            'post_id' => $post_id,
        ]);
    }

    /**
     * set postable
     *
     * @param string $postable_type
     * @param int $postable_id
     *
     * @return static
     */
    public function setPostable(string $postable_type, int $postable_id): static
    {
        return $this->state(fn(array $attributes) => [
            'postable_type' => $postable_type,
            'postable_id' => $postable_id,
        ]);
    }

    /**
     * set collection
     *
     * @param string|null $collection
     *
     * @return static
     */
    public function setCollection(?string $collection): static
    {
        return $this->state(fn(array $attributes) => [
            'collection' => $collection,
        ]);
    }
}
