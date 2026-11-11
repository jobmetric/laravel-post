<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Model;

/** Record an editorial lock for every field of one post language. */
class PostLocaleLock extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['locked' => 'boolean'];

    /** Resolve the host-configured locale lock table. */
    public function getTable(): string
    {
        return config('post.tables.locale_lock', 'post_locale_locks');
    }
}
