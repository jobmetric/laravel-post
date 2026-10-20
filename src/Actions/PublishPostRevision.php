<?php

namespace JobMetric\Post\Actions;

use JobMetric\Post\Contracts\ScheduledPostAction;
use JobMetric\Post\Models\PostContentRevision;
use JobMetric\Post\Models\PostPublicationStream;
use JobMetric\Post\Models\PostScheduledAction;

/** Publish the approved revision selected when the schedule was created. */
class PublishPostRevision implements ScheduledPostAction
{
    /**
     * Return the built-in publication action key.
     *
     * @return string
     */
    public function key(): string
    {
        return 'publish';
    }

    /**
     * Point the live stream to the approved immutable revision.
     *
     * @param PostPublicationStream $stream
     * @param PostContentRevision $revision
     * @param PostScheduledAction $schedule
     *
     * @return void
     */
    public function execute(PostPublicationStream $stream, PostContentRevision $revision, PostScheduledAction $schedule): void
    {
        $stream->live_revision_id = $revision->id;
        $stream->published_at = now();
    }
}
