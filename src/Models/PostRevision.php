<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retain an immutable snapshot of post-owned content.
 */
class PostRevision extends Model
{
    public $timestamps = false;
    protected $fillable = ['snapshot'];
    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime'];
    /**
     * Resolve the model table from the package configuration.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('post.tables.revision', 'post_revisions');
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
