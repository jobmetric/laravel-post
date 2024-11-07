<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use JobMetric\Category\Events\CategorizableResourceEvent;

/**
 * JobMetric\Post\Models\PostRelation
 *
 * @property int $relatable_type
 * @property int $relatable_id
 * @property int $post_id
 * @property int $collection
 *
 * @property Post $post
 * @property mixed $postable
 * @property mixed $postable_resource
 */
class PostRelation extends Pivot
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'post_id',
        'postable_type',
        'postable_id',
        'collection'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'post_id' => 'integer',
        'postable_type' => 'string',
        'postable_id' => 'integer',
        'collection' => 'string'
    ];

    public function getTable()
    {
        return config('post.tables.post_relation', parent::getTable());
    }

    /**
     * Get the post that owns the relation.
     *
     * @return BelongsTo
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the postable model that owns the post.
     *
     * @return MorphTo
     */
    public function postable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include posts of a given type.
     *
     * @param Builder $query
     * @param string $collection
     * @return Builder
     */
    public function scopeByCollection(Builder $query, string $collection): Builder
    {
        return $query->where('collection', $collection);
    }

    /**
     * Get the postable resource attribute.
     */
    // public function getPostResourceAttribute()
    // {
    //     $event = new CategorizableResourceEvent($this->postable);
    //     event($event);

    //     return $event->resource;
    // }
}
