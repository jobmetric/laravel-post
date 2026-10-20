<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Model;

/** Bind an executable action to an approved immutable revision and a due time. */
class PostScheduledAction extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime'];

    /**
     * Resolve the configurable scheduled action table.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('post.tables.schedule', 'post_scheduled_actions');
    }
}
