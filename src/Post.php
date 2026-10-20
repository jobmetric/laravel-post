<?php

namespace JobMetric\Post;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use JobMetric\PackageCore\Output\Response;
use JobMetric\Post\Models\Post as PostModel;
use JobMetric\Post\Models\PostLocaleLock;
use JobMetric\Post\Models\PostPublicationStream;
use JobMetric\Post\Models\PostWorkflowDefinition;
use JobMetric\Post\Services\PostWorkflow;
use JobMetric\Post\Support\PostData;
use JobMetric\Post\Support\PostTypeRegistry;
use Throwable;
use Illuminate\Validation\ValidationException;

/**
 * Persist complete post aggregates without depending on an administration panel.
 */
class Post
{
    /**
     * Resolve the type registry, validation policy and workflow coordinator.
     *
     * @param PostTypeRegistry $registry
     * @param PostData $data
     * @param PostWorkflow $workflow
     *
     * @return void
     */
    public function __construct(
        protected PostTypeRegistry $registry,
        protected PostData $data,
        protected PostWorkflow $workflow
    ) {
    }

    /**
     * Start a query scoped to a registered post type.
     *
     * @param string $type
     * @param list<string> $with
     *
     * @return Builder<PostModel>
     */
    public function query(string $type, array $with = []): Builder
    {
        $this->registry->ensureExists($type);

        return PostModel::query()->ofType($type)->with($with);
    }

    /**
     * Create a post and its owned translations, metadata and associations.
     *
     * @param string $type
     * @param array<string, mixed> $data
     * @param Authenticatable|null $actor
     *
     * @return Response
     * @throws Throwable
     */
    public function store(string $type, array $data, ?Authenticatable $actor = null): Response
    {
        $this->registry->ensureExists($type);
        $post = new PostModel();
        $post->type = $type;

        return $this->persist($post, $data, $actor);
    }

    /**
     * Lock and update a post while preserving omitted fields and collections.
     *
     * @param string $type
     * @param int $id
     * @param array<string, mixed> $data
     * @param Authenticatable|null $actor
     *
     * @return Response
     * @throws Throwable
     */
    public function update(string $type, int $id, array $data, ?Authenticatable $actor = null): Response
    {
        return (new PostModel())->getConnection()->transaction(function () use ($type, $id, $data, $actor): Response {
            $post = $this->query($type)->lockForUpdate()->findOrFail($id);

            return $this->persist($post, $data, $actor);
        });
    }

    /**
     * Soft-delete a post through its model lifecycle.
     *
     * @param string $type
     * @param int $id
     *
     * @return Response
     * @throws Throwable
     */
    public function delete(string $type, int $id): Response
    {
        return (new PostModel())->getConnection()->transaction(function () use ($type, $id): Response {
            $post = $this->query($type)->lockForUpdate()->findOrFail($id);
            $post->delete();
            if ($post->trashed()) {
                app(\JobMetric\EventSystem\Support\CommittedEventDispatcher::class)->dispatch(new \JobMetric\Post\Events\Post\PostDeleteCommittedEvent($post->getKey()), $post->getConnectionName());
            }

            return Response::make(true, trans('post::base.deleted'), ['id' => $id]);
        });
    }

    /**
     * Validate and replace selected media collections without affecting others.
     *
     * @param PostModel $post
     * @param array<string, list<int>> $collections
     *
     * @return void
     * @throws Throwable
     */
    public function syncMedia(PostModel $post, array $collections): void
    {
        $this->data->validate($post, ['media' => $collections]);
        $post->getConnection()->transaction(function () use ($post, $collections): void {
            $outcomeBefore = \JobMetric\EventSystem\Support\OutcomeState::capture($post, ['files']);
            foreach ($collections as $collection => $ids) {
                $outcomeExistingIds = $post->files()->wherePivot('collection', $collection)->pluck((new \JobMetric\Media\Models\Media)->getTable().'.id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();
                $outcomeRequestedIds = array_map('intval', $ids);
                sort($outcomeRequestedIds);
                if ($outcomeExistingIds === array_values(array_unique($outcomeRequestedIds))) {
                    continue;
                }
                $post->files()->wherePivot('collection', $collection)->detach();
                foreach ($ids as $id) {
                    $post->attachMedia((int) $id, $collection);
                }
            }
            $post->unsetRelation('files');
            if ($outcomeBefore !== \JobMetric\EventSystem\Support\OutcomeState::capture($post, ['files'])) {
                app(\JobMetric\EventSystem\Support\CommittedEventDispatcher::class)->dispatch(new \JobMetric\Post\Events\PostMedia\PostMediaSyncCommittedEvent($post->getKey()), $post->getConnectionName());
            }
        });
    }

    /**
     * Replace selected taxonomy collections through their package policies.
     *
     * @param PostModel $post
     * @param array<string, list<int>> $collections
     *
     * @return void
     * @throws Throwable
     */
    public function syncTaxonomies(PostModel $post, array $collections): void
    {
        $this->data->validate($post, ['taxonomies' => $collections]);
        $post->getConnection()->transaction(function () use ($post, $collections): void {
            $outcomeBefore = \JobMetric\EventSystem\Support\OutcomeState::capture($post, ['taxonomies']);
            foreach ($collections as $collection => $ids) {
                $outcomeExistingIds = $post->taxonomies()->wherePivot('collection', $collection)->pluck((new \JobMetric\Taxonomy\Models\Taxonomy)->getTable().'.id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();
                $outcomeRequestedIds = array_map('intval', $ids);
                sort($outcomeRequestedIds);
                if ($outcomeExistingIds === array_values(array_unique($outcomeRequestedIds))) {
                    continue;
                }
                $post->taxonomies()->wherePivot('collection', $collection)->detach();
                foreach ($ids as $id) {
                    $post->attachTaxonomy((int) $id, $collection);
                }
            }
            $post->unsetRelation('taxonomies');
            if ($outcomeBefore !== \JobMetric\EventSystem\Support\OutcomeState::capture($post, ['taxonomies'])) {
                app(\JobMetric\EventSystem\Support\CommittedEventDispatcher::class)->dispatch(new \JobMetric\Post\Events\PostTaxonomy\PostTaxonomySyncCommittedEvent($post->getKey()), $post->getConnectionName());
            }
        });
    }

    /**
     * Commit validated aggregate changes and retain the previous revision.
     *
     * @param PostModel $post
     * @param array<string, mixed> $data
     * @param Authenticatable|null $actor
     *
     * @return Response
     * @throws Throwable
     */
    protected function persist(PostModel $post, array $data, ?Authenticatable $actor): Response
    {
        return $post->getConnection()->transaction(function () use ($post, $data, $actor): Response {
            $data = $this->data->validate($post, $data);
            if ($post->exists) {
                $stream = PostPublicationStream::where('post_id', $post->id)
                    ->where('locale', PostPublicationStream::SHARED)->first();
                $definition = $stream?->definition_id ? PostWorkflowDefinition::find($stream->definition_id) : null;
                if ($definition?->enabled && $post->status !== 'editable') {
                    throw ValidationException::withMessages(['status' => [trans('post::lifecycle.not_editable')]]);
                }
                foreach (array_keys((array) ($data['translation'] ?? [])) as $locale) {
                    if (PostLocaleLock::where('post_id', $post->id)->where('locale', $locale)->where('locked', true)->exists()) {
                        throw ValidationException::withMessages(['translation.'.$locale => [trans('post::lifecycle.language_locked')]]);
                    }
                }
            }
            $before = $post->exists ? $post->snapshot() : null;
            $lastRevision = $post->exists ? (int) $post->revisions()->max('id') : 0;
            $isNew = ! $post->exists;
            $target = (string) ($data['status'] ?? $post->status ?? 'draft');
            $post->fill(Arr::only($data, ['published_at', 'comment_status', 'password', 'slug', 'metadata']));
            if ($isNew) {
                $post->status = $post->pickFlow()?->states()->start()->value('status') ?: $target;
            }
            $translations = (array) ($data['translation'] ?? []);
            foreach ($translations as $locale => &$fields) {
                foreach ($fields as $field => &$value) {
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
                unset($value);
                if (! $isNew) {
                    $fields = array_filter($fields, fn ($value, $field): bool => $post->getTranslation((string) $field, (string) $locale) !== $value, ARRAY_FILTER_USE_BOTH);
                }
            }
            unset($fields);
            $post->translation = array_filter($translations);
            $post->withoutRevisionRecording(function () use ($post, $target, $actor, $isNew): void {
                $post->save();
                $this->workflow->transition($post, $target, $actor, $isNew);
            });
            $this->syncTaxonomies($post, (array) ($data['taxonomies'] ?? []));
            $this->syncMedia($post, (array) ($data['media'] ?? []));
            if ($before !== null && $post->snapshot() !== $before
                && (int) $post->revisions()->max('id') === $lastRevision) {
                $post->revisions()->create(['snapshot' => $before]);
            }

            if ($isNew || $before !== $post->snapshot()) {
                $outcome = $isNew ? new \JobMetric\Post\Events\Post\PostStoreCommittedEvent($post->getKey()) : new \JobMetric\Post\Events\Post\PostUpdateCommittedEvent($post->getKey());
                app(\JobMetric\EventSystem\Support\CommittedEventDispatcher::class)->dispatch($outcome, $post->getConnectionName());
            }

            return Response::make(true, trans('post::base.saved'), ['post' => $post]);
        });
    }
}
