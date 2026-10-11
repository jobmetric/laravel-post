<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Represent a post attachment in a named polymorphic collection.
 */
class PostRelation extends Model
{
    public $timestamps = false;
    protected $fillable = ['post_id', 'postable_type', 'postable_id', 'collection'];
    protected $casts = ['post_id' => 'integer', 'postable_id' => 'integer'];

    /**
     * Create the factory associated with this package model.
     *
     * @return Factory
     */
    protected static function newFactory(): Factory
    {
        return \JobMetric\Post\Factories\PostRelationFactory::new();
    }

    /**
     * Resolve the model table from the package configuration.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('post.tables.post_relation', 'post_relations');
    }

    /**
     * Relate this record to its owning post.
     *
     * @return BelongsTo
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
