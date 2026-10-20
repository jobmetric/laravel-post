<?php

namespace JobMetric\Post\Contracts;

use JobMetric\Post\Models\PostContentRevision;
use JobMetric\Post\Models\PostPublicationStream;
use JobMetric\Post\Models\PostScheduledAction;

/** Execute a scheduled action against its approved, immutable revision. */
interface ScheduledPostAction
{
    /**
     * Return the stable key stored with scheduled actions.
     *
     * @return string
     */
    public function key(): string;

    /**
     * Persist transactional effects for an approved revision.
     *
     * @param PostPublicationStream $stream
     * @param PostContentRevision $revision
     * @param PostScheduledAction $schedule
     *
     * @return void
     */
    public function execute(PostPublicationStream $stream, PostContentRevision $revision, PostScheduledAction $schedule): void;
}
