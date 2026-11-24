<?php

namespace JobMetric\Post\Events\Workflow;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;

/**
 * Describe a committed Workflow created outcome using allowlisted identifiers.
 */
readonly class WorkflowStoreCommittedEvent implements DomainEvent
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
        return 'post.workflow.stored.committed';
    }

    /**
     * Describe the outcome for translated discovery interfaces.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'post::automation.workflow_stored.group', 'post::automation.workflow_stored.title', 'post::automation.workflow_stored.description', 'fas fa-bolt', ['automation', 'committed', 'post']);
    }
}
