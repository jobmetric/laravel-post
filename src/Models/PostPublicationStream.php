<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JobMetric\Flow\HasWorkflow;
use JobMetric\Flow\Models\Flow;

/** Track one language or the shared public fields independently of the live revision. */
class PostPublicationStream extends Model
{
    use HasWorkflow;

    public const SHARED = '*';

    protected $guarded = ['id'];
    protected $casts = ['published_at' => 'datetime', 'version' => 'integer'];

    /**
     * Resolve the configurable publication stream table.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('post.tables.stream', 'post_publication_streams');
    }

    /**
     * Resolve the owner of this publication stream.
     *
     * @return BelongsTo
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Bind only through explicit workflow activation, never an automatic picker.
     *
     * @return Flow|null
     */
    public function pickFlow(): ?Flow
    {
        return null;
    }
}
