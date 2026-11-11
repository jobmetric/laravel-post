<?php

namespace JobMetric\Post\Tasks;

use JobMetric\Flow\Contracts\AbstractActionTask;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Post\Contracts\WorkflowTaskConfiguration;
use JobMetric\Post\Models\PostLocaleLock;
use JobMetric\Post\Models\PostPublicationStream;

/** Apply one configured language lock inside a post transition transaction. */
abstract class SetLocaleLock extends AbstractActionTask implements WorkflowTaskConfiguration
{
    use PostTaskConfiguration;

    abstract protected function locked(): bool;

    public function configurationRules(): array
    {
        return ['locale' => ['required', 'string', 'max:32', 'regex:/^[a-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/']];
    }

    public function configurationFields(): array
    {
        return ['locale' => ['type' => 'text', 'label' => 'post::lifecycle.tasks.locale_key', 'required' => true]];
    }

    protected function handle(FlowTaskContext $context): void
    {
        $stream = $context->subject();
        if (! $stream instanceof PostPublicationStream || $stream->locale !== PostPublicationStream::SHARED) {
            throw new \LogicException('Language lock tasks require a whole-post workflow.');
        }
        PostLocaleLock::query()->updateOrCreate(
            ['post_id' => $stream->post_id, 'locale' => (string) $context->config()['locale']],
            ['locked' => $this->locked(), 'changed_by' => $context->user()?->getAuthIdentifier()],
        );
    }
}
