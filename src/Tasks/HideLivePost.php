<?php

namespace JobMetric\Post\Tasks;

use JobMetric\Flow\Contracts\AbstractActionTask;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Flow\Support\FlowTaskDefinition;
use JobMetric\Post\Contracts\WorkflowTaskConfiguration;
use JobMetric\Post\Models\PostPublicationStream;
use JobMetric\Post\Services\PostLifecycle;

/** Withdraw the previous live release only when a transition explicitly asks for it. */
class HideLivePost extends AbstractActionTask implements WorkflowTaskConfiguration
{
    use PostTaskConfiguration;

    public static function definition(): FlowTaskDefinition
    {
        return new FlowTaskDefinition('post::lifecycle.tasks.hide_live', 'post::lifecycle.tasks.hide_live_description', 'eye-slash', ['action']);
    }

    protected function handle(FlowTaskContext $context): void
    {
        $stream = $context->subject();
        if (! $stream instanceof PostPublicationStream || $stream->locale !== PostPublicationStream::SHARED) {
            throw new \LogicException('The live release belongs to the whole post.');
        }
        $stream->live_revision_id = null;
        $stream->published_at = null;
        $stream->save();
        app(PostLifecycle::class)->record($stream, 'live_hidden', $context->user());
    }
}
