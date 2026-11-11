<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Preserve the exact content submitted, approved or selected for publication. */
class PostContentRevision extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['payload' => 'array', 'created_at' => 'datetime'];

    /**
     * Prevent mutation of an already captured revision.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Content revisions are immutable.');
        });
    }

    /**
     * Resolve the configurable content revision table.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('post.tables.content_revision', 'post_content_revisions');
    }
}
