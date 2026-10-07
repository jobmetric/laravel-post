<?php

namespace JobMetric\Post\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Post\Enums\PostStatusEnum;
use JobMetric\Post\Models\Post;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(PostStatusEnum::values());

        $published_at = null;
        if ($status == PostStatusEnum::PUBLISH()) {
            $published_at = Carbon::now();
        } elseif ($status == PostStatusEnum::FUTURE()) {
            $published_at = fake()->dateTime("+1 week");
        }

        return [
            'type' => 'post',
            'comment_status' => fake()->boolean(),
            'password' => fake()->optional(0.3)->word(),
            'status' => $status,
            'published_at' => $published_at
        ];
    }

    /**
     * set type
     *
     * @param string $type
     *
     * @return static
     */
    public function setType(string $type): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => $type
        ]);
    }

    /**
     * set comment status
     *
     * @param bool $comment_status
     *
     * @return static
     */
    public function setCommentStatus(bool $comment_status): static
    {
        return $this->state(fn(array $attributes) => [
            'comment_status' => $comment_status
        ]);
    }

    /**
     * set password
     *
     * @param string|null $password
     *
     * @return static
     */
    public function setPassword(?string $password): static
    {
        return $this->state(fn(array $attributes) => [
            'password' => $password
        ]);
    }

    /**
     * set status
     *
     * @param string $status
     *
     * @return static
     */
    public function setStatus(string $status): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => $status
        ]);
    }
}
