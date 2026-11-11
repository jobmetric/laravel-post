<?php

namespace JobMetric\Post\Tasks;

use JobMetric\Flow\Contracts\AbstractActionTask;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Post\Contracts\WorkflowTaskConfiguration;
use JobMetric\Post\Models\PostPublicationStream;
use JobMetric\Post\Services\PostLifecycle;

/** Record execution of an explicitly configured audit task in the transition transaction. */
class RecordTransition extends AbstractActionTask implements WorkflowTaskConfiguration
{
    use PostTaskConfiguration;

    public function configurationRules(): array
    {
        return ['note' => ['sometimes', 'nullable', 'string', 'max:160']];
    }

    public function configurationFields(): array
    {
        return ['note' => ['type' => 'text', 'label' => 'post::lifecycle.tasks.audit_note', 'required' => false]];
    }

    /**
     * Describe the audit action in the translated task library.
     *
     * @return FlowTaskDefinition
     */
    public static function definition(): FlowTaskDefinition
    {
        return new FlowTaskDefinition('post::lifecycle.tasks.audit', 'post::lifecycle.tasks.audit_description', 'history', ['action']);
    }

    /**
     * Append an audit entry containing identifiers only.
     *
     * @param FlowTaskContext $context
     *
     * @return void
     */
    protected function handle(FlowTaskContext $context): void
    {
        $stream = $context->subject();
        if (! $stream instanceof PostPublicationStream) {
            throw new \LogicException('Post audit tasks require a publication stream.');
        }
        app(PostLifecycle::class)->record($stream, 'audit_task', $context->user(), [
            'note' => (string) ($context->config()['note'] ?? ''),
        ]);
    }
}
