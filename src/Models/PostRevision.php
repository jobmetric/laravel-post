<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostRevision extends Model
{
    public $timestamps = false;
    protected $fillable = ['snapshot'];
    protected $casts = ['snapshot' => 'array', 'created_at' => 'datetime'];
    public function getTable(): string { return config('post.tables.revision', 'post_revisions'); }
    public function post(): BelongsTo { return $this->belongsTo(Post::class, 'post_id'); }
}
