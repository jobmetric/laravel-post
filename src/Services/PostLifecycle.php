<?php

namespace JobMetric\Post\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JobMetric\Post\Enums\ScheduledEditPolicy;
use JobMetric\Post\Models\Post;
use JobMetric\Post\Models\PostContentRevision;
use JobMetric\Post\Models\PostPublicationStream;
use JobMetric\Post\Models\PostScheduledAction;
use JobMetric\Post\Models\PostWorkflowDefinition;
use JobMetric\Post\Support\ContentFingerprint;
use JobMetric\Post\Support\PostTypeRegistry;
use JobMetric\Post\Support\ScheduledPostActionRegistry;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Coordinate immutable revisions, approvals and publication under one owner lock. */
class PostLifecycle
{
    /**
     * Capture changed content while preserving all earlier approvals and live content.
     *
     * @param Post $post
     * @param string $locale
     * @param array<string, mixed> $payload
     * @param Authenticatable|null $actor
     * @param int|null $version
     *
     * @return PostPublicationStream
     */
    public function saveDraft(Post $post, string $locale, array $payload, ?Authenticatable $actor = null, ?int $version = null): PostPublicationStream
    {
        return $this->mutate($post, $locale, $version, function (PostPublicationStream $stream) use ($payload, $actor, $post): void {
            $checksum = ContentFingerprint::hash($payload);
            if ($stream->draft_revision_id && PostContentRevision::findOrFail($stream->draft_revision_id)->checksum === $checksum) {
                return;
            }
            $revision = PostContentRevision::create([
                'stream_id' => $stream->id, 'payload' => $payload, 'checksum' => $checksum,
                'created_by' => $actor?->getAuthIdentifier(), 'created_at' => now(),
            ]);
            $stream->draft_revision_id = $revision->id;
            $ids = $stream->locale === PostPublicationStream::SHARED
                ? PostPublicationStream::where('post_id', $post->id)->pluck('id') : [$stream->id];
            $schedules = PostScheduledAction::whereIn('stream_id', $ids)->where('state', 'pending')
                ->where('edit_policy', ScheduledEditPolicy::Pause->value)->lockForUpdate()->get();
            foreach ($schedules as $schedule) {
                $schedule->update(['state' => 'paused']);
                $this->record($stream, 'schedule_paused', $actor, ['schedule_id' => $schedule->id], (int) $schedule->revision_id);
            }
            $this->record($stream, 'draft_saved', $actor);
        });
    }

    /**
     * Submit the exact current revision for review without changing the live version.
     *
     * @param Post $post
     * @param string $locale
     * @param int $revisionId
     * @param Authenticatable|null $actor
     * @param int|null $version
     *
     * @return PostPublicationStream
     */
    public function submit(Post $post, string $locale, int $revisionId, ?Authenticatable $actor = null, ?int $version = null): PostPublicationStream
    {
        return $this->mutate($post, $locale, $version, function (PostPublicationStream $stream) use ($revisionId, $actor): void {
            $this->requireDraft($stream, $revisionId);
            $stream->submitted_revision_id = $revisionId;
            $this->record($stream, 'submitted', $actor, [], $revisionId);
        });
    }

    /**
     * Approve a specified current revision without resuming any scheduled action.
     *
     * @param Post $post
     * @param string $locale
     * @param int $revisionId
     * @param Authenticatable|null $actor
     * @param int|null $version
     *
     * @return PostPublicationStream
     */
    public function approve(Post $post, string $locale, int $revisionId, ?Authenticatable $actor = null, ?int $version = null): PostPublicationStream
    {
        return $this->mutate($post, $locale, $version, function (PostPublicationStream $stream) use ($revisionId, $actor): void {
            $this->requireDraft($stream, $revisionId);
            $definition = PostWorkflowDefinition::find($stream->definition_id);
            if ($definition?->enabled && $stream->locale !== PostPublicationStream::SHARED) {
                $state = collect($definition->graph['states'])->firstWhere('status', $stream->status);
                $reviewed = DB::table(config('post.tables.lifecycle_event', 'post_lifecycle_events'))
                    ->where('stream_id', $stream->id)->where('revision_id', $revisionId)
                    ->where('event', 'transition')->orderByDesc('id')->first();
                $context = $reviewed ? json_decode($reviewed->context, true, 512, JSON_THROW_ON_ERROR) : [];
                if (! ($state['allows_approval'] ?? false)
                    || ($context['to'] ?? null) !== $stream->status
                    || (int) ($context['definition_id'] ?? 0) !== (int) $definition->id) {
                    throw ValidationException::withMessages(['revision_id' => [trans('post::lifecycle.review_required')]]);
                }
            }
            DB::table(config('post.tables.approval', 'post_revision_approvals'))->insertOrIgnore([
                'revision_id' => $revisionId, 'approved_by' => $actor?->getAuthIdentifier(), 'created_at' => now(),
            ]);
            $stream->approved_revision_id = $revisionId;
            $this->record($stream, 'approved', $actor, [], $revisionId);
        });
    }

    /**
     * Reject a submitted revision while retaining the published and approved versions.
     *
     * @param Post $post
     * @param string $locale
     * @param int $revisionId
     * @param Authenticatable|null $actor
     * @param int|null $version
     *
     * @return PostPublicationStream
     */
    public function reject(Post $post, string $locale, int $revisionId, ?Authenticatable $actor = null, ?int $version = null): PostPublicationStream
    {
        return $this->mutate($post, $locale, $version, function (PostPublicationStream $stream) use ($revisionId, $actor): void {
            if ((int) $stream->submitted_revision_id !== $revisionId) {
                throw new ConflictHttpException('The submitted revision has changed.');
            }
            $stream->submitted_revision_id = null;
            $this->record($stream, 'rejected', $actor, ['rejected_revision_id' => $revisionId], $revisionId);
        });
    }

    /**
     * Make an approved revision live without reading mutable draft data.
     *
     * @param Post $post
     * @param string $locale
     * @param int $revisionId
     * @param Authenticatable|null $actor
     * @param int|null $version
     *
     * @return PostPublicationStream
     */
    public function publish(Post $post, string $locale, int $revisionId, ?Authenticatable $actor = null, ?int $version = null): PostPublicationStream
    {
        return $this->mutate($post, $locale, $version, function (PostPublicationStream $stream) use ($revisionId, $actor): void {
            if ($stream->locale !== PostPublicationStream::SHARED) {
                $this->requireApproval($stream, $revisionId);
            } elseif (! PostContentRevision::where('stream_id', $stream->id)->whereKey($revisionId)->exists()) {
                throw new ConflictHttpException('The aggregate revision has changed.');
            }
            $stream->live_revision_id = $revisionId;
            $stream->published_at = now();
            $this->record($stream, 'published', $actor, ['published_revision_id' => $revisionId], $revisionId);
        });
    }

    /**
     * Publish a restored whole-post draft as a new immutable release.
     *
     * The source revision remains in history; even identical content receives
     * a fresh revision identifier so the restoration is auditable.
     */
    public function publishRestoration(Post $post, ?Authenticatable $actor = null): PostPublicationStream
    {
        return $this->mutate($post, PostPublicationStream::SHARED, null, function (PostPublicationStream $stream) use ($actor): void {
            if (! $stream->draft_revision_id) {
                throw new ConflictHttpException('The restored draft is missing.');
            }
            $source = PostContentRevision::where('stream_id', $stream->id)
                ->findOrFail($stream->draft_revision_id);
            $release = PostContentRevision::create([
                'stream_id' => $stream->id,
                'payload' => $source->payload,
                'checksum' => $source->checksum,
                'created_by' => $actor?->getAuthIdentifier(),
                'created_at' => now(),
            ]);
            $stream->draft_revision_id = $release->id;
            $stream->live_revision_id = $release->id;
            $stream->published_at = now();
            $this->record($stream, 'restored_published', $actor, [
                'source_revision_id' => (int) $source->id,
            ], (int) $release->id);
        });
    }

    /**
     * Schedule an approved version or explicitly replace a paused schedule after approval.
     *
     * @param Post $post
     * @param string $locale
     * @param int $revisionId
     * @param CarbonInterface $dueAt
     * @param Authenticatable|null $actor
     * @param int|null $resumeId
     * @param int|null $version
     * @param string $action
     *
     * @return PostScheduledAction
     */
    public function schedule(Post $post, string $locale, int $revisionId, CarbonInterface $dueAt, ?Authenticatable $actor = null, ?int $resumeId = null, ?int $version = null, string $action = 'publish'): PostScheduledAction
    {
        app(ScheduledPostActionRegistry::class)->get($action);
        $schedule = null;
        $this->mutate($post, $locale, $version, function (PostPublicationStream $stream) use ($post, $revisionId, $dueAt, $actor, $resumeId, $action, &$schedule): void {
            if ($stream->locale === PostPublicationStream::SHARED) {
                if (! PostContentRevision::where('stream_id', $stream->id)->whereKey($revisionId)->exists()) {
                    throw new ConflictHttpException('The aggregate revision has changed.');
                }
            } else {
                $this->requireApproval($stream, $revisionId);
            }
            if ($dueAt->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['due_at' => [trans('post::lifecycle.future_required')]]);
            }
            $existing = PostScheduledAction::where('stream_id', $stream->id)->whereIn('state', ['pending', 'paused'])->lockForUpdate()->get();
            if ($resumeId !== null) {
                $schedule = $existing->firstWhere('id', $resumeId);
                if (! $schedule || $schedule->state !== 'paused') {
                    throw new ConflictHttpException('The paused schedule has changed.');
                }
                if ($schedule->action !== $action) {
                    throw new ConflictHttpException('The scheduled action cannot change when resuming.');
                }
                $this->requireDraft($stream, $revisionId);
                $schedule->update(['revision_id' => $revisionId, 'due_at' => $dueAt, 'state' => 'pending']);
            } else {
                if ($existing->isNotEmpty()) {
                    throw ValidationException::withMessages(['schedule' => [trans('post::lifecycle.schedule_exists')]]);
                }
                $schedule = PostScheduledAction::create([
                    'stream_id' => $stream->id, 'revision_id' => $revisionId, 'action' => $action,
                    'state' => 'pending', 'due_at' => $dueAt, 'execution_key' => (string) Str::uuid(),
                    'edit_policy' => app(PostTypeRegistry::class)->for($post->type)->getScheduledEditPolicy()->value,
                    'created_by' => $actor?->getAuthIdentifier(),
                ]);
            }
            $this->record($stream, $resumeId ? 'schedule_resumed' : 'scheduled', $actor, ['schedule_id' => $schedule->id], $revisionId);
            $stream->version++;
        });

        return $schedule;
    }

    /**
     * Execute one due publication once, serialized against saved content and schedule changes.
     *
     * @param int $scheduleId
     *
     * @return bool
     */
    public function execute(int $scheduleId): bool
    {
        $schedule = PostScheduledAction::findOrFail($scheduleId);
        $stream = PostPublicationStream::findOrFail($schedule->stream_id);
        $post = $stream->post;
        if (! $post) {
            DB::transaction(static function () use ($scheduleId): void {
                $orphan = PostScheduledAction::lockForUpdate()->findOrFail($scheduleId);
                if ($orphan->state === 'pending') {
                    $orphan->update(['state' => 'cancelled']);
                }
            });
            return false;
        }
        $executed = false;
        $this->mutate($post, $stream->locale, null, function (PostPublicationStream $stream) use ($post, $scheduleId, &$executed): void {
            $schedule = PostScheduledAction::lockForUpdate()->findOrFail($scheduleId);
            if ($schedule->state !== 'pending' || $schedule->due_at->isFuture()
                || ($stream->locale === PostPublicationStream::SHARED && $post->status !== 'future')) {
                return;
            }
            if ($stream->locale !== PostPublicationStream::SHARED) {
                $this->requireApproval($stream, (int) $schedule->revision_id);
            }
            $revision = PostContentRevision::where('stream_id', $stream->id)->findOrFail($schedule->revision_id);
            app(ScheduledPostActionRegistry::class)->get($schedule->action)->execute($stream, $revision, $schedule);
            if ($stream->locale === PostPublicationStream::SHARED && $schedule->action === 'publish') {
                $definition = PostWorkflowDefinition::find($stream->definition_id);
                $publishState = $definition ? collect($definition->graph['states'])
                    ->firstWhere('content_status', 'publish') : null;
                if ($publishState) {
                    $stream->status = $publishState['status'];
                }
                $stream->content_status = 'publish';
                $stream->post()->update(['status' => 'publish', 'published_at' => now()]);
            }
            $schedule->update(['state' => 'completed', 'completed_at' => now()]);
            $this->record($stream, 'scheduled_action_completed', null, ['schedule_id' => $schedule->id], (int) $schedule->revision_id);
            $executed = true;
        });

        return $executed;
    }

    /**
     * Read approved public snapshots for exactly the requested language.
     *
     * @param Post $post
     * @param string $locale
     *
     * @return array<string, mixed>|null
     */
    public function published(Post $post, string $locale): ?array
    {
        if ($post->trashed() || $locale === PostPublicationStream::SHARED || $post->status === 'disabled') {
            return null;
        }
        $aggregate = PostPublicationStream::where('post_id', $post->id)
            ->where('locale', PostPublicationStream::SHARED)->first();
        if ($aggregate && ! $aggregate->live_revision_id) {
            return null;
        }
        if ($aggregate?->live_revision_id) {
            $revision = PostContentRevision::where('stream_id', $aggregate->id)
                ->findOrFail($aggregate->live_revision_id);
            $payload = $revision->payload;
            if (! isset($payload['languages'][$locale])) {
                return null;
            }

            return [
                'id' => $post->id, 'type' => $post->type, 'locale' => $locale,
                'revision_id' => $revision->id,
                'content' => $payload['languages'][$locale],
                'shared' => $payload['shared'] ?? [],
                'published_at' => $aggregate->published_at?->toIso8601String(),
            ];
        }
        $streams = PostPublicationStream::where('post_id', $post->id)->whereIn('locale', [$locale, PostPublicationStream::SHARED])->get()->keyBy('locale');
        $stream = $streams->get($locale);
        if (! $stream?->live_revision_id) {
            return null;
        }
        $shared = $streams->get(PostPublicationStream::SHARED);

        return [
            'id' => $post->id, 'type' => $post->type, 'locale' => $locale,
            'revision_id' => $stream->live_revision_id,
            'content' => PostContentRevision::where('stream_id', $stream->id)->findOrFail($stream->live_revision_id)->payload,
            'shared' => $shared?->live_revision_id ? PostContentRevision::where('stream_id', $shared->id)->findOrFail($shared->live_revision_id)->payload : [],
            'published_at' => $stream->published_at?->toIso8601String(),
        ];
    }

    /**
     * Cancel a pending or paused program while preserving its audit record.
     *
     * @param Post $post
     * @param string $locale
     * @param int $scheduleId
     * @param int $version
     * @param Authenticatable|null $actor
     *
     * @return PostPublicationStream
     */
    public function cancelSchedule(Post $post, string $locale, int $scheduleId, int $version, ?Authenticatable $actor = null): PostPublicationStream
    {
        return $this->mutate($post, $locale, $version, function (PostPublicationStream $stream) use ($scheduleId, $actor): void {
            $schedule = PostScheduledAction::where('stream_id', $stream->id)->lockForUpdate()->findOrFail($scheduleId);
            if (! in_array($schedule->state, ['pending', 'paused'], true)) {
                throw new ConflictHttpException('The schedule has already finished or been cancelled.');
            }
            $schedule->update(['state' => 'cancelled']);
            $stream->version++;
            $this->record($stream, 'schedule_cancelled', $actor, ['schedule_id' => $schedule->id], (int) $schedule->revision_id);
        });
    }

    /**
     * Withdraw a language from public delivery and cancel its unfinished programs.
     *
     * @param Post $post
     * @param string $locale
     * @param int $version
     * @param Authenticatable|null $actor
     *
     * @return PostPublicationStream
     */
    public function withdraw(Post $post, string $locale, int $version, ?Authenticatable $actor = null): PostPublicationStream
    {
        return $this->mutate($post, $locale, $version, function (PostPublicationStream $stream) use ($actor): void {
            $stream->live_revision_id = null;
            $stream->published_at = null;
            $this->cancelSchedules($stream, $actor);
            $stream->version++;
            $this->record($stream, 'withdrawn', $actor);
        });
    }

    /**
     * Serialize mutations using the type, owner and language lock order.
     *
     * @param Post $post
     * @param string $locale
     * @param int|null $version
     * @param callable(PostPublicationStream):void $callback
     *
     * @return PostPublicationStream
     */
    public function mutate(Post $post, string $locale, ?int $version, callable $callback): PostPublicationStream
    {
        if ($locale === '' || strlen($locale) > 32) {
            throw new \InvalidArgumentException('A valid stream locale is required.');
        }

        return DB::transaction(function () use ($post, $locale, $version, $callback): PostPublicationStream {
            $workflow = app(PostWorkflowDefinitions::class)->lock($post->type);
            Post::lockForUpdate()->findOrFail($post->id);
            // The caller's model may predate a concurrent transition. Re-read it
            // after taking the owner lock before checking status or acting on it.
            $post->refresh();
            $stream = PostPublicationStream::firstOrCreate(['post_id' => $post->id, 'locale' => $locale], ['status' => 'draft']);
            $stream = PostPublicationStream::lockForUpdate()->findOrFail($stream->id);
            if ($version !== null && $stream->version !== $version) {
                throw new ConflictHttpException('The publication stream has changed.');
            }
            if ($stream->wasRecentlyCreated || $stream->definition_id === null) {
                app(PostWorkflowDefinitions::class)->bindNewStream($workflow, $stream);
            }
            $originalVersion = (int) $stream->version;
            $callback($stream);
            if ($stream->isDirty()) {
                $stream->version = $originalVersion + 1;
                $stream->save();
            }

            return $stream->refresh();
        });
    }

    /**
     * Cancel active programs and describe each changed schedule after commit.
     *
     * @param PostPublicationStream $stream
     * @param Authenticatable|null $actor
     *
     * @return void
     */
    public function cancelSchedules(PostPublicationStream $stream, ?Authenticatable $actor = null): void
    {
        foreach (PostScheduledAction::where('stream_id', $stream->id)->whereIn('state', ['pending', 'paused'])->lockForUpdate()->get() as $schedule) {
            $schedule->update(['state' => 'cancelled']);
            $this->record($stream, 'schedule_cancelled', $actor, ['schedule_id' => $schedule->id], (int) $schedule->revision_id);
        }
    }

    /**
     * Reject attempts to approve or submit a replaced draft.
     *
     * @param PostPublicationStream $stream
     * @param int $revisionId
     *
     * @return void
     */
    private function requireDraft(PostPublicationStream $stream, int $revisionId): void
    {
        if ((int) $stream->draft_revision_id !== $revisionId || $revisionId < 1) {
            throw new ConflictHttpException('The draft revision has changed.');
        }
    }

    /**
     * Verify both revision ownership and its immutable approval record.
     *
     * @param PostPublicationStream $stream
     * @param int $revisionId
     *
     * @return void
     */
    private function requireApproval(PostPublicationStream $stream, int $revisionId): void
    {
        if (! PostContentRevision::where('stream_id', $stream->id)->whereKey($revisionId)->exists()
            || ! DB::table(config('post.tables.approval', 'post_revision_approvals'))->where('revision_id', $revisionId)->exists()) {
            throw ValidationException::withMessages(['revision_id' => [trans('post::lifecycle.approval_required')]]);
        }
    }

    /**
     * Append an audit event without copying content or credentials.
     *
     * @param PostPublicationStream $stream
     * @param string $event
     * @param Authenticatable|null $actor
     * @param array<string, int|string> $context
     * @param int|null $revisionId
     *
     * @return void
     */
    public function record(PostPublicationStream $stream, string $event, ?Authenticatable $actor, array $context = [], ?int $revisionId = null): void
    {
        DB::table(config('post.tables.lifecycle_event', 'post_lifecycle_events'))->insert([
            'post_id' => $stream->post_id, 'stream_id' => $stream->id,
            'revision_id' => $revisionId ?? $stream->draft_revision_id, 'event' => $event,
            'actor_id' => $actor?->getAuthIdentifier(), 'context' => json_encode($context, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);

        $outcomeClass = match ($event) {
            'draft_saved' => \JobMetric\Post\Events\PostRevision\PostRevisionStoreCommittedEvent::class,
            'submitted' => \JobMetric\Post\Events\Publication\PublicationSubmitCommittedEvent::class,
            'approved' => \JobMetric\Post\Events\Publication\PublicationApproveCommittedEvent::class,
            'rejected' => \JobMetric\Post\Events\Publication\PublicationRejectCommittedEvent::class,
            'published' => \JobMetric\Post\Events\Publication\PublicationPublishCommittedEvent::class,
            'restored_published' => \JobMetric\Post\Events\Publication\PublicationRestoreCommittedEvent::class,
            'schedule_paused' => \JobMetric\Post\Events\Schedule\SchedulePauseCommittedEvent::class,
            'scheduled' => \JobMetric\Post\Events\Schedule\ScheduleStoreCommittedEvent::class,
            'schedule_resumed' => \JobMetric\Post\Events\Schedule\ScheduleResumeCommittedEvent::class,
            'scheduled_action_completed' => \JobMetric\Post\Events\Schedule\ScheduleExecuteCommittedEvent::class,
            'schedule_cancelled' => \JobMetric\Post\Events\Schedule\ScheduleCancelCommittedEvent::class,
            'withdrawn' => \JobMetric\Post\Events\Publication\PublicationWithdrawCommittedEvent::class,
            'transition' => \JobMetric\Post\Events\Publication\PublicationTransitionCommittedEvent::class,
            'workflow_migrated' => \JobMetric\Post\Events\Publication\PublicationUpdateCommittedEvent::class,
            default => null,
        };
        $outcomeChanged = match ($event) {
            'draft_saved' => $stream->isDirty('draft_revision_id'),
            'submitted' => $stream->isDirty('submitted_revision_id'),
            'approved' => $stream->isDirty('approved_revision_id'),
            'rejected' => $stream->isDirty('submitted_revision_id'),
            'published' => $stream->isDirty('live_revision_id'),
            'restored_published' => true,
            'schedule_paused' => true,
            'scheduled' => true,
            'schedule_resumed' => true,
            'scheduled_action_completed' => true,
            'schedule_cancelled' => true,
            'withdrawn' => $stream->isDirty(['live_revision_id', 'published_at']),
            'transition' => ($context['from'] ?? null) !== ($context['to'] ?? null),
            'workflow_migrated' => true,
            default => false,
        };
        $outcomeStreamId = isset($context['schedule_id'])
            ? (PostScheduledAction::find((int) $context['schedule_id'])?->stream_id ?? $stream->id) : $stream->id;
        if ($outcomeClass && $outcomeChanged) {
            app(\JobMetric\EventSystem\Support\CommittedEventDispatcher::class)->dispatch(new $outcomeClass(
                (int) $stream->post_id, (int) $outcomeStreamId, $revisionId ?? $stream->draft_revision_id,
                $actor?->getAuthIdentifier(), isset($context['schedule_id']) ? (int) $context['schedule_id'] : null
            ), $stream->getConnectionName());
        }
    }
}
