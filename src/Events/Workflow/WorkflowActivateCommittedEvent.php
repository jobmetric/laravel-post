<?php

namespace JobMetric\Post\Events\Workflow;

use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;

/**
 * Describe a committed Workflow activated outcome using allowlisted identifiers.
 */
readonly class WorkflowActivateCommittedEvent implements DomainEvent
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
        return 'post.workflow.activated.committed';
    }

    /**
     * Describe the outcome for translated discovery interfaces.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'post::automation.workflow_activated.group', 'post::automation.workflow_activated.title', 'post::automation.workflow_activated.description', 'fas fa-bolt', ['automation', 'committed', 'post']);
    }
}
