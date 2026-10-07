<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostRelation extends Model
{
    public $timestamps = false;
    protected $fillable = ['post_id', 'postable_type', 'postable_id', 'collection'];
    protected $casts = ['post_id' => 'integer', 'postable_id' => 'integer'];

    protected static function newFactory(): Factory { return \JobMetric\Post\Factories\PostRelationFactory::new(); }

    public function getTable(): string { return config('post.tables.post_relation', 'post_relations'); }

    public function post(): BelongsTo { return $this->belongsTo(Post::class, 'post_id'); }
}
