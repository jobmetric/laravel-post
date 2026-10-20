<?php

namespace JobMetric\Post\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JobMetric\Flow\Facades\FlowTransition;
use JobMetric\Post\Exceptions\PostStatusTransitionNotAllowedException;
use JobMetric\Post\Models\Post;
use JobMetric\Post\Models\PostContentRevision;
use JobMetric\Post\Models\PostPublicationStream;
use JobMetric\Post\Models\PostScheduledAction;
use JobMetric\Post\Models\PostWorkflowDefinition;
use JobMetric\Post\Support\PostTypeRegistry;

/** Execute the versioned workflow of one whole post against its exact aggregate revision. */
class PostStreamWorkflow
{
    /**
     * Run an allowed transition under the same locks used by publication and migration.
     *
     * @param Post $post
     * @param string $locale
     * @param string $transitionKey
     * @param int $revisionId
     * @param int $version
     * @param Authenticatable|null $actor
     *
     * @return PostPublicationStream
     * @throws \Throwable
     */
    public function transition(Post $post, string $locale, string $transitionKey, int $revisionId, int $version, ?Authenticatable $actor, ?CarbonInterface $dueAt = null): PostPublicationStream
    {
        if ($locale !== PostPublicationStream::SHARED) {
            throw new PostStatusTransitionNotAllowedException('Only the post aggregate can advance its workflow.');
        }
        return app(PostLifecycle::class)->mutate($post, $locale, $version, function (PostPublicationStream $stream) use ($post, $transitionKey, $revisionId, $actor, $dueAt): void {
            $definition = PostWorkflowDefinition::find($stream->definition_id);
            if (! $definition?->enabled || (int) $stream->draft_revision_id !== $revisionId) {
                throw new PostStatusTransitionNotAllowedException('The workflow or draft revision has changed.');
            }
            $nodes = collect($definition->graph['states'])->keyBy('id');
            $edge = collect($definition->graph['transitions'])->firstWhere('slug', $transitionKey);
            if (! $edge || $nodes[$edge['from']]['status'] !== $stream->status) {
                throw new PostStatusTransitionNotAllowedException('The transition is not available from the current stage.');
            }
            $flow = $stream->boundFlow();
            if (! $flow || (int) $flow->id !== (int) $definition->flow_id) {
                throw new PostStatusTransitionNotAllowedException('The workflow binding has changed.');
            }
            $transition = $flow->transitions()->where('slug', $transitionKey)->firstOrFail();
            $revision = PostContentRevision::where('stream_id', $stream->id)->findOrFail($revisionId);
            $previous = $stream->status;
            FlowTransition::runner((int) $transition->id, $stream, [
                'revision_id' => $revisionId, 'content' => $revision->payload,
            ], $actor);
            $stream->refresh();
            $destination = $nodes[$edge['to']];
            $stream->content_status = $destination['content_status'] ?? $stream->content_status ?? 'editable';
            if ($stream->content_status === 'future') {
                if (! $dueAt || $dueAt->lessThanOrEqualTo(now())) {
                    throw ValidationException::withMessages(['due_at' => [trans('post::lifecycle.future_required')]]);
                }
                app(PostLifecycle::class)->cancelSchedules($stream, $actor);
                $outcomeSchedule = PostScheduledAction::create([
                    'stream_id' => $stream->id,
                    'revision_id' => $revisionId,
                    'action' => 'publish',
                    'state' => 'pending',
                    'edit_policy' => app(PostTypeRegistry::class)->for($post->type)->getScheduledEditPolicy()->value,
                    'due_at' => $dueAt,
                    'execution_key' => (string) Str::uuid(),
                    'created_by' => $actor?->getAuthIdentifier(),
                ]);
                app(PostLifecycle::class)->record($stream, 'scheduled', $actor, ['schedule_id' => $outcomeSchedule->id], $revisionId);
            } else {
                app(PostLifecycle::class)->cancelSchedules($stream, $actor);
            }
            $post->status = $stream->content_status;
            $post->published_at = $stream->content_status === 'future' ? $dueAt : ($stream->content_status === 'publish' ? now() : $post->published_at);
            $post->save();
            if ($post->status === 'publish') {
                $stream->live_revision_id = $revisionId;
                $stream->published_at = now();
            }
            app(PostLifecycle::class)->record($stream, 'transition', $actor, [
                'from' => $previous, 'to' => $stream->status, 'transition' => $transitionKey,
                'definition_id' => $definition->id,
            ]);
        });
    }
}
