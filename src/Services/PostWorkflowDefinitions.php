<?php

namespace JobMetric\Post\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JobMetric\Flow\Models\Flow;
use JobMetric\Flow\Models\FlowState;
use JobMetric\Flow\Models\FlowTransition;
use JobMetric\Post\Models\Post;
use JobMetric\Post\Models\PostPublicationStream;
use JobMetric\Post\Models\PostScheduledAction;
use JobMetric\Post\Models\PostWorkflowDefinition;
use JobMetric\Post\Support\ContentFingerprint;
use JobMetric\Post\Support\PostTypeRegistry;
use JobMetric\Post\Support\WorkflowGraph;
use stdClass;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Publish workflow definitions only after a reviewed, concurrency-checked state mapping. */
class PostWorkflowDefinitions
{
    /**
     * Lock the type-level coordinator before locking any publication stream.
     *
     * @param string $type
     *
     * @return stdClass
     */
    public function lock(string $type): stdClass
    {
        app(PostTypeRegistry::class)->ensureExists($type);
        $table = DB::table(config('post.tables.workflow', 'post_workflows'));
        $table->insertOrIgnore(['type' => $type, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $table->where('type', $type)->lockForUpdate()->first();
    }

    /**
     * Save a new immutable draft while retaining the currently active definition.
     *
     * @param string $type
     * @param array<string, mixed> $graph
     * @param bool $enabled
     * @param int $expectedVersion
     * @param Authenticatable|null $actor
     *
     * @return PostWorkflowDefinition
     */
    public function saveDraft(string $type, array $graph, bool $enabled, int $expectedVersion, ?Authenticatable $actor = null): PostWorkflowDefinition
    {
        $graph = app(WorkflowGraph::class)->validate($graph, false);

        return DB::transaction(function () use ($type, $graph, $enabled, $expectedVersion, $actor): PostWorkflowDefinition {
            $workflow = $this->lock($type);
            if ((int) $workflow->version !== $expectedVersion) {
                throw new ConflictHttpException('The workflow draft has changed.');
            }
            $version = $expectedVersion + 1;
            $definition = PostWorkflowDefinition::create([
                'workflow_id' => $workflow->id, 'version' => $version,
                'enabled' => $enabled, 'state' => 'draft', 'graph' => $graph,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);
            DB::table(config('post.tables.workflow', 'post_workflows'))->where('id', $workflow->id)->update(['version' => $version, 'updated_at' => now()]);

            app(\JobMetric\EventSystem\Support\CommittedEventDispatcher::class)->dispatch(new \JobMetric\Post\Events\Workflow\WorkflowStoreCommittedEvent($definition->getKey()), $definition->getConnectionName());

            return $definition;
        });
    }

    /**
     * Show mapping choices and a fingerprint of the exact affected stream versions.
     *
     * @param string $type
     * @param int $definitionId
     *
     * @return array<string, mixed>
     */
    public function preview(string $type, int $definitionId): array
    {
        return DB::transaction(function () use ($type, $definitionId): array {
            $workflow = $this->lock($type);
            $definition = PostWorkflowDefinition::where('workflow_id', $workflow->id)->where('state', 'draft')->findOrFail($definitionId);
            app(WorkflowGraph::class)->validate($definition->graph, (bool) $definition->enabled);
            $streams = $this->streams($type)->orderBy('id')->lockForUpdate()
                ->get(['id', 'status', 'version', 'live_revision_id']);
            $scheduled = PostScheduledAction::query()->whereIn('stream_id', $streams->pluck('id'))
                ->whereIn('state', ['pending', 'paused'])->get()->groupBy('stream_id');
            $targets = $definition->enabled ? array_column($definition->graph['states'], 'status') : ['draft'];
            $mapping = [];
            foreach ($streams->groupBy('status') as $status => $rows) {
                $mapping[] = [
                    'from' => $status, 'count' => $rows->count(),
                    'live_count' => $rows->whereNotNull('live_revision_id')->count(),
                    'scheduled_count' => $rows->sum(fn ($stream): int => $scheduled->get($stream->id, collect())->count()),
                    'to' => in_array($status, $targets, true) ? $status : null,
                ];
            }

            return [
                'definition_id' => $definition->id, 'enabled' => $definition->enabled,
                'targets' => $targets, 'mapping' => $mapping,
                'fingerprint' => ContentFingerprint::hash([
                    'workflow_version' => (int) $workflow->version,
                    'active_definition_id' => $workflow->active_definition_id,
                    'definition_id' => $definition->id, 'graph' => $definition->graph,
                    'streams' => $streams->toArray(),
                ]),
            ];
        });
    }

    /**
     * Activate a reviewed definition and migrate stages without executing transition tasks.
     *
     * @param string $type
     * @param int $definitionId
     * @param string $fingerprint
     * @param array<string, string> $mapping
     * @param Authenticatable|null $actor
     *
     * @return PostWorkflowDefinition
     */
    public function activate(string $type, int $definitionId, string $fingerprint, array $mapping, ?Authenticatable $actor = null): PostWorkflowDefinition
    {
        return DB::transaction(function () use ($type, $definitionId, $fingerprint, $mapping, $actor): PostWorkflowDefinition {
            $workflow = $this->lock($type);
            $preview = $this->preview($type, $definitionId);
            if (! hash_equals($preview['fingerprint'], $fingerprint)) {
                throw new ConflictHttpException('Workflow impact changed. Review a fresh mapping preview.');
            }
            foreach ($preview['mapping'] as $row) {
                if (! isset($mapping[$row['from']]) || ! in_array($mapping[$row['from']], $preview['targets'], true)) {
                    throw ValidationException::withMessages(['mapping.'.$row['from'] => [trans('post::lifecycle.mapping_required')]]);
                }
            }
            $definition = PostWorkflowDefinition::findOrFail($definitionId);
            app(WorkflowGraph::class)->validate($definition->graph, (bool) $definition->enabled);
            $flow = $definition->enabled ? $this->compile($type, $definition) : null;
            foreach ($this->streams($type)->orderBy('id')->get() as $stream) {
                $previous = $stream->status;
                $stream->status = $mapping[$previous];
                $targetState = collect($definition->graph['states'])->firstWhere('status', $stream->status);
                $stream->content_status = $targetState['content_status'] ?? $stream->content_status ?? 'editable';
                $stream->definition_id = $definition->id;
                $stream->version++;
                $stream->save();
                $stream->post()->update(['status' => $stream->content_status]);
                $flow ? $stream->bindFlow($flow) : $stream->unbindFlow();
                app(PostLifecycle::class)->record($stream, 'workflow_migrated', $actor, ['from' => $previous, 'to' => $stream->status, 'definition_id' => $definition->id]);
            }
            if ($workflow->active_definition_id) {
                PostWorkflowDefinition::whereKey($workflow->active_definition_id)->update(['state' => 'retired']);
            }
            $definition->update(['state' => 'active', 'flow_id' => $flow?->id]);
            DB::table(config('post.tables.workflow', 'post_workflows'))->where('id', $workflow->id)->update([
                'active_definition_id' => $definition->id, 'version' => $workflow->version + 1, 'updated_at' => now(),
            ]);

            app(\JobMetric\EventSystem\Support\CommittedEventDispatcher::class)->dispatch(new \JobMetric\Post\Events\Workflow\WorkflowActivateCommittedEvent($definition->getKey()), $definition->getConnectionName());

            return $definition;
        });
    }

    /**
     * Start a newly created stream in the explicitly active type definition.
     *
     * @param stdClass $workflow
     * @param PostPublicationStream $stream
     *
     * @return void
     */
    public function bindNewStream(stdClass $workflow, PostPublicationStream $stream): void
    {
        if (! $workflow->active_definition_id || $stream->locale !== PostPublicationStream::SHARED) {
            return;
        }
        $definition = PostWorkflowDefinition::findOrFail($workflow->active_definition_id);
        $stream->definition_id = $definition->id;
        if ($definition->enabled) {
            $startState = collect($definition->graph['states'])->firstWhere('is_start', true);
            $stream->status = $startState['status'];
            $stream->content_status = $startState['content_status'] ?? 'editable';
            $stream->bindFlow(Flow::findOrFail($definition->flow_id));
        }
    }

    /**
     * Find the single workflow stream of each post in the requested type.
     *
     * @param string $type
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function streams(string $type): \Illuminate\Database\Eloquent\Builder
    {
        return PostPublicationStream::where('locale', PostPublicationStream::SHARED)
            ->whereIn('post_id', Post::withTrashed()->where('type', $type)->select('id'));
    }

    /**
     * Compile a validated graph into an isolated laravel-flow definition.
     *
     * @param string $type
     * @param PostWorkflowDefinition $definition
     *
     * @return Flow
     */
    private function compile(string $type, PostWorkflowDefinition $definition): Flow
    {
        $flow = Flow::create([
            'subject_type' => PostPublicationStream::class, 'subject_collection' => $type,
            'version' => $definition->version, 'status' => true, 'is_default' => false,
        ]);
        $ids = [];
        foreach ($definition->graph['states'] as $node) {
            $state = FlowState::create([
                'flow_id' => $flow->id, 'status' => $node['status'],
                'type' => $node['is_start'] ? 'start' : 'state',
                'config' => ['is_terminal' => (bool) $node['is_terminal']],
            ]);
            foreach ($node['translations'] ?? [] as $locale => $translation) {
                $state->translate($locale, $translation);
            }
            $ids[$node['id']] = $state->id;
            if ($node['is_start']) {
                FlowTransition::create(['flow_id' => $flow->id, 'from' => null, 'to' => $state->id, 'slug' => '__start__']);
            }
        }
        foreach ($definition->graph['transitions'] as $edge) {
            $transition = FlowTransition::create(['flow_id' => $flow->id, 'from' => $ids[$edge['from']], 'to' => $ids[$edge['to']], 'slug' => $edge['slug']]);
            foreach ($edge['translations'] ?? [] as $locale => $translation) {
                $transition->translate($locale, $translation);
            }
            foreach ($edge['tasks'] as $ordering => $task) {
                $transition->tasks()->create(['driver' => $task['driver'], 'config' => $task['config'], 'ordering' => $ordering, 'status' => $task['status'] ?? true]);
            }
        }

        return $flow;
    }
}
