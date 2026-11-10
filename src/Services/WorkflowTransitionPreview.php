<?php

namespace JobMetric\Post\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use JobMetric\Flow\Contracts\AbstractActionTask;
use JobMetric\Flow\DTO\TransitionResult;
use JobMetric\Flow\Support\FlowTaskContext;
use JobMetric\Post\Contracts\WorkflowConditionPreview;
use JobMetric\Post\Models\PostContentRevision;
use JobMetric\Post\Models\PostPublicationStream;
use JobMetric\Post\Tasks\RequireApprovedRevision;
use JobMetric\Post\Tasks\RequireContent;
use JobMetric\Post\Tasks\RequireExcerpt;
use JobMetric\Post\Tasks\RequirePermission;

/** Evaluate registered read-only conditions without running transition actions or lifecycle hooks. */
class WorkflowTransitionPreview
{
    /** Inspect roles and the immutable aggregate revision used by real execution.
     *
     * @param array<string, mixed> $edge
     * @param PostPublicationStream $stream
     * @param Authenticatable $actor
     *
     * @return array{status: string, checks: array<int, array<string, mixed>>}
     */
    public function inspect(array $edge, PostPublicationStream $stream, Authenticatable $actor): array
    {
        $checks = [];
        if (! app(WorkflowTransitionAccess::class)->allows($edge, $actor)) {
            return ['status' => 'blocked', 'checks' => [['message' => trans('post::lifecycle.role_denied')]]];
        }
        $revision = PostContentRevision::where('stream_id', $stream->id)->find($stream->draft_revision_id);
        if (! $revision) return ['status' => 'blocked', 'checks' => [['message' => trans('post::lifecycle.content_required')]]];
        $payload = ['revision_id' => $revision->id, 'content' => $revision->payload];
        $status = 'ready';
        foreach ($edge['tasks'] ?? [] as $task) {
            if (! ($task['status'] ?? true)) continue;
            $class = $task['driver'];
            if (is_subclass_of($class, AbstractActionTask::class)) continue;
            if (! is_subclass_of($class, WorkflowConditionPreview::class) && ! in_array($class, [RequireApprovedRevision::class, RequirePermission::class, RequireContent::class, RequireExcerpt::class], true)) {
                $checks[] = ['driver' => $class, 'allowed' => null, 'message' => ''];
                if ($status !== 'blocked') $status = 'unknown';
                continue;
            }
            $driver = app($class);
            $context = (new FlowTaskContext($stream, new TransitionResult, $payload, $actor))->replaceConfig($task['config'] ?? []);
            $allowed = null;
            $message = '';
            if ($driver instanceof WorkflowConditionPreview) {
                $result = $driver->preview($context);
                $allowed = $result['allowed'];
                $message = $result['message'];
            } elseif (in_array($class, [RequireApprovedRevision::class, RequirePermission::class], true)) {
                $result = $driver->restriction($context);
                $allowed = $result->allowed();
                $message = $result->message() ?? '';
            } elseif (in_array($class, [RequireContent::class, RequireExcerpt::class], true)) {
                $errors = Validator::make($payload, $driver->rules($context), $driver->messages($context), $driver->attributes($context))->errors();
                $allowed = $errors->isEmpty();
                $message = $errors->first();
            }
            $checks[] = ['driver' => $class, 'allowed' => $allowed, 'message' => $message];
            if ($allowed === false) $status = 'blocked';
            elseif ($allowed === null && $status !== 'blocked') $status = 'unknown';
        }
        return compact('status', 'checks');
    }
}
