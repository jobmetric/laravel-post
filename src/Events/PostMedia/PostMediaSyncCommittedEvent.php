<?php

namespace JobMetric\Post\Events\PostMedia;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;

/**
 * Describe a committed PostMedia synchronized outcome using allowlisted identifiers.
 */
readonly class PostMediaSyncCommittedEvent implements DomainEvent
{
    /**
     * Capture identifiers without models, input values or credentials.
     *
     * @param int|string $id
     *
     * @return void
     */
    public function __construct(
        public int|string $id
    ) {
    }

    /**
     * Return the stable key used by automation consumers.
     *
     * @return string
     */
    public static function key(): string
    {
        return 'post.post_media.synced.committed';
    }

    /**
     * Describe the outcome for translated discovery interfaces.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'post::automation.post_media_synced.group', 'post::automation.post_media_synced.title', 'post::automation.post_media_synced.description', 'fas fa-bolt', ['automation', 'committed', 'post']);
    }
}
