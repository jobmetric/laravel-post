<?php

namespace JobMetric\Post\Events\Publication;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;

/**
 * Describe a committed Publication submitted outcome using allowlisted identifiers.
 */
readonly class PublicationSubmitCommittedEvent implements DomainEvent
{
    /**
     * Capture identifiers without models, input values or credentials.
     *
     * @param int $postId
     * @param int $streamId
     * @param ?int $revisionId
     * @param int|string|null $actorId
     * @param ?int $scheduleId
     *
     * @return void
     */
    public function __construct(
        public int $postId,
        public int $streamId,
        public ?int $revisionId,
        public int|string|null $actorId,
        public ?int $scheduleId
    ) {
    }

    /**
     * Return the stable key used by automation consumers.
     *
     * @return string
     */
    public static function key(): string
    {
        return 'post.publication.submitted.committed';
    }

    /**
     * Describe the outcome for translated discovery interfaces.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'post::automation.publication_submitted.group', 'post::automation.publication_submitted.title', 'post::automation.publication_submitted.description', 'fas fa-bolt', ['automation', 'committed', 'post']);
    }
}
