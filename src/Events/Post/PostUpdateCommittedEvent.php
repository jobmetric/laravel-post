<?php

namespace JobMetric\Post\Events\Post;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;

/**
 * Describe a committed Post updated outcome using allowlisted identifiers.
 */
readonly class PostUpdateCommittedEvent implements DomainEvent
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
        return 'post.post.updated.committed';
    }

    /**
     * Describe the outcome for translated discovery interfaces.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'post::automation.post_updated.group', 'post::automation.post_updated.title', 'post::automation.post_updated.description', 'fas fa-bolt', ['automation', 'committed', 'post']);
    }
}
