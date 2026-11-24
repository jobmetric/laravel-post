<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use JobMetric\Flow\HasWorkflow;
use JobMetric\Flow\Models\Flow;
use JobMetric\Media\Contracts\MediaContract;
use JobMetric\Media\HasFile;
use JobMetric\Metadata\HasMeta;
use JobMetric\Post\Contracts\PostContract;
use JobMetric\Post\Factories\PostFactory;
use JobMetric\Post\Support\PostTypeRegistry;
use JobMetric\Taxonomy\Contracts\TaxonomyContract;
use JobMetric\Taxonomy\Facades\TaxonomyTypeRegistry;
use JobMetric\Taxonomy\HasTaxonomy;
use JobMetric\Translation\HasTranslation;
use JobMetric\Url\Contracts\UrlContract;
use JobMetric\Url\HasUrl;

/**
 * Represent a typed content aggregate with translations, metadata and revisions.
 */
class Post extends Model implements MediaContract, PostContract, TaxonomyContract, UrlContract
{
    use HasFactory, SoftDeletes, HasWorkflow, HasTranslation, HasMeta, HasFile, HasTaxonomy, HasUrl {
        HasTranslation::translate as protected persistTranslation;
        HasMeta::storeMetadata as protected persistMetadata;
        HasWorkflow::pickFlow as protected pickConfiguredFlow;
    }

    protected $fillable = [
        'type',
        'comment_status',
        'password',
        'status',
        'published_at',
        'translation',
        'metadata',
        'slug',
        'slug_collection',
    ];
    protected $casts = [
        'comment_status' => 'boolean',
        'published_at'   => 'datetime',
    ];
    protected array $translatables = ['*'];
    protected array $metadata = ['*'];

    private ?array $revisionState = null;
    private bool $recordRevisions = true;

    /**
     * Resolve the shared registry used to enforce model capabilities.
     *
     * @return PostTypeRegistry
     */
    public static function typeRegistry(): PostTypeRegistry
    {
        return app(PostTypeRegistry::class);
    }
    /**
     * Create the factory associated with this package model.
     *
     * @return Factory
     */
    protected static function newFactory(): Factory
    {
        return PostFactory::new();
    }
    /**
     * Resolve the model table from the package configuration.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('post.tables.post', 'posts');
    }
    /**
     * Resolve the table used to retain post revisions.
     *
     * @return string
     */
    public function getRevisionTable(): string
    {
        return config('post.tables.revision', 'post_revisions');
    }

    /**
     * Relate attached media using the pivot's creation timestamp only.
     *
     * @return MorphToMany
     */
    public function files(): MorphToMany
    {
        return $this->morphToMany(\JobMetric\Media\Models\Media::class, 'mediaable', config('media.tables.media_relation'))
            ->withPivot('collection')
            ->withTimestamps('created_at', false);
    }

    /**
     * Relate assigned taxonomy terms using the pivot's creation timestamp only.
     *
     * @return MorphToMany
     */
    public function taxonomies(): MorphToMany
    {
        return $this->morphToMany(\JobMetric\Taxonomy\Models\Taxonomy::class, 'taxonomizable', config('taxonomy.tables.taxonomy_relation'))
            ->withPivot('collection')
            ->withTimestamps('created_at', false);
    }

    /**
     * Resolve named taxonomy collections and verify their registered types.
     *
     * @return array<string, mixed>
     */
    public function taxonomyAllowTypes(): array
    {
        $types = (array) static::typeRegistry()
            ->getOption((string) $this->type, 'taxonomy-types', []);

        foreach ($types as $collection => $definition) {
            $taxonomyType = (string) ($definition['type'] ?? '');
            if ($taxonomyType === '' || ! TaxonomyTypeRegistry::has($taxonomyType)) {
                throw new \InvalidArgumentException("Taxonomy type [{$taxonomyType}] in post collection [{$collection}] is not registered.");
            }
        }

        return $types;
    }

    /**
     * Return the translation keys allowed by the current post type.
     *
     * @return list<string>
     */
    public function translationAllowFields(): array
    {
        return (array) static::typeRegistry()
            ->getOption((string) $this->type, 'translation-fields', ['title', 'excerpt', 'content']);
    }

    /**
     * Validate translated field names before persisting one locale.
     *
     * @param string $locale
     * @param array<string, mixed> $data
     *
     * @return static
     * @throws \InvalidArgumentException
     */
    public function translate(string $locale, array $data): static
    {
        $allowed = $this->translationAllowFields();
        $disallowed = array_filter(array_keys($data), fn (string $field): bool => ! in_array('*', $allowed, true) && ! in_array($field, $allowed, true));
        if ($disallowed !== []) {
            throw new \InvalidArgumentException('Translation fields are not allowed for this post type: ' . implode(', ', $disallowed));
        }

        return $this->persistTranslation($locale, $data);
    }

    /**
     * Reject unregistered translated and metadata fields during assignment.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'translation' && is_array($value)) {
            $type = (string) ($this->attributes['type'] ?? '');
            if ($type !== '' && static::typeRegistry()
                    ->has($type)) {
                $allowed = $this->translationAllowFields();
                foreach ($value as $locale => $fields) {
                    if (! is_array($fields)) {
                        throw new \InvalidArgumentException("Translation payload for locale [{$locale}] must be an array.");
                    }

                    $disallowed = array_filter(array_keys($fields), fn (string $field): bool => ! in_array('*', $allowed, true) && ! in_array($field, $allowed, true));
                    if ($disallowed !== []) {
                        throw new \InvalidArgumentException('Translation fields are not allowed for this post type: ' . implode(', ', $disallowed));
                    }
                }
            }
        }

        if ($key === 'metadata' && is_array($value)) {
            $type = (string) ($this->attributes['type'] ?? '');
            if ($type !== '' && static::typeRegistry()
                    ->has($type)) {
                $allowed = (array) static::typeRegistry()
                    ->getOption($type, 'metadata-fields', []);
                $disallowed = array_filter(array_keys($value), fn (string $field): bool => ! in_array('*', $allowed, true) && ! in_array($field, $allowed, true));
                if ($disallowed !== []) {
                    throw new \InvalidArgumentException('Metadata fields are not allowed for this post type: ' . implode(', ', $disallowed));
                }
            }
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Return the metadata keys allowed by the current post type.
     *
     * @return list<string>
     */
    public function metadataAllowFields(): array
    {
        return (array) static::typeRegistry()
            ->getOption((string) $this->type, 'metadata-fields', []);
    }

    /**
     * Validate a metadata key before persisting its value.
     *
     * @param string $key
     * @param array|string|bool|null $value
     *
     * @return static
     * @throws \InvalidArgumentException
     */
    public function storeMetadata(string $key, array|string|bool|null $value = null): static
    {
        $allowed = (array) static::typeRegistry()
            ->getOption((string) $this->type, 'metadata-fields', []);
        if (! in_array('*', $allowed, true) && ! in_array($key, $allowed, true)) {
            throw new \InvalidArgumentException("Metadata field [{$key}] is not allowed for post type [{$this->type}].");
        }

        return $this->persistMetadata($key, $value);
    }

    /**
     * Resolve media policies including the reserved editor collection.
     *
     * @return array<string, mixed>
     */
    public function mediaAllowCollections(): array
    {
        $collections = (array) static::typeRegistry()
            ->getOption((string) $this->type, 'media-collections', []);
        $collections['editor'] ??= ['media_collection' => 'public', 'size' => [], 'multiple' => true, 'mimeTypes' => ['image']];
        foreach ($collections as &$definition) {
            $definition['media_collection'] = $definition['mediaCollection'] ?? $definition['media_collection'] ?? 'public';
            $definition['size'] ??= [];
        }

        return $collections;
    }

    /**
     * Combine the registered website prefix with the current slug.
     *
     * @return string
     */
    public function getFullUrl(): string
    {
        $prefix = trim((string) static::typeRegistry()
            ->getOption((string) $this->type, 'url-prefix', ''), '/');
        $slug = trim((string) ($this->slug ?? ''), '/');

        return trim($prefix . '/' . $slug, '/');
    }

    /**
     * Resolve the workflow collection associated with this post type.
     *
     * @return string|null
     */
    protected function flowSubjectCollection(): ?string
    {
        $type = (string) $this->type;

        return $type === '' ? null : static::typeRegistry()
            ->getOption($type, 'workflow');
    }

    /**
     * Bind legacy flows only when the post type explicitly requests one.
     *
     * @return Flow|null
     */
    public function pickFlow(): ?Flow
    {
        return $this->flowSubjectCollection() === null ? null : $this->pickConfiguredFlow();
    }

    /**
     * Relate snapshots ordered from newest to oldest.
     *
     * @return HasMany
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class, 'post_id')
            ->latest('id');
    }

    /**
     * Restrict a query to one post type.
     *
     * @param Builder $query
     * @param string $type
     *
     * @return Builder
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
    /**
     * Restrict a query to published posts whose publication date has arrived.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['disabled', 'archive'])
            ->whereHas('publicationStreams', fn (Builder $stream): Builder => $stream
                ->where('locale', PostPublicationStream::SHARED)
                ->whereNotNull('live_revision_id')
                ->where('published_at', '<=', now()));
    }

    /**
     * Query live content in one language without trusting the legacy global status.
     *
     * @param Builder $query
     * @param string $locale
     *
     * @return Builder
     */
    public function scopeLiveInLocale(Builder $query, string $locale): Builder
    {
        $posts = $this->getTable();
        $streams = config('post.tables.stream', 'post_publication_streams');
        $revisions = config('post.tables.content_revision', 'post_content_revisions');

        return $query->where('status', '!=', 'disabled')
            ->whereExists(static fn ($inner) => $inner->selectRaw('1')->from($streams)
            ->join($revisions, $revisions.'.id', '=', $streams.'.live_revision_id')
            ->whereColumn($streams.'.post_id', $posts.'.id')
            ->where($streams.'.locale', PostPublicationStream::SHARED)
            ->whereNotNull($streams.'.live_revision_id')
            ->where($streams.'.published_at', '<=', now())
            ->whereJsonContainsKey($revisions.'.payload->languages->'.$locale));
    }

    /**
     * Relate all language and shared publication streams to their owner.
     *
     * @return HasMany
     */
    public function publicationStreams(): HasMany
    {
        return $this->hasMany(PostPublicationStream::class);
    }

    /**
     * Register capability validation, revision capture and relation cleanup.
     *
     * @return void
     */
    public static function booted(): void
    {
        static::saving(function (self $post): void {
            $type = (string) $post->type;
            static::typeRegistry()
                ->ensureExists($type);
            $allowedTranslations = (array) static::typeRegistry()
                ->getOption($type, 'translation-fields', ['title', 'excerpt', 'content']);
            foreach ($post->innerTranslations as $locale => $fields) {
                $disallowed = array_filter(array_keys($fields), fn (string $field): bool => ! in_array('*', $allowedTranslations, true) && ! in_array($field, $allowedTranslations, true));
                if ($disallowed !== []) {
                    throw new \InvalidArgumentException('Translation fields are not allowed for this post type: ' . implode(', ', $disallowed));
                }
            }

            $allowed = (array) static::typeRegistry()
                ->getOption($type, 'metadata-fields', []);
            $metadata = $post->innerMeta;
            $disallowed = array_filter(array_keys($metadata), fn (string $field): bool => ! in_array('*', $allowed, true) && ! in_array($field, $allowed, true));
            if ($disallowed !== []) {
                throw new \InvalidArgumentException('Metadata fields are not allowed for this post type: ' . implode(', ', $disallowed));
            }
        });

        static::updating(function (self $post): void {
            $post->revisionState = $post->recordRevisions ? $post->fresh()
                ->snapshot() : null;
        });
        static::updated(function (self $post): void {
            if ($post->revisionState !== null) {
                $post->revisions()
                    ->create(['snapshot' => $post->revisionState]);
                $post->revisionState = null;
            }
        });
        static::forceDeleted(function (self $post): void {
            app(\JobMetric\EventSystem\Support\CommittedEventDispatcher::class)->dispatch(new \JobMetric\Post\Events\Post\PostForceDeleteCommittedEvent($post->getKey()), $post->getConnectionName());
            $post->files()
                ->detach();
            $post->taxonomies()
                ->detach();
        });
    }

    /**
     * Capture post-owned state without copying shared media or taxonomy records.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'attributes' => $this->only(['type', 'comment_status', 'password', 'status', 'published_at', 'slug', 'slug_collection']),
            'translation' => $this->translations()
                ->orderBy('locale')
                ->orderBy('field')
                ->get(['locale', 'field', 'value'])
                ->map(fn ($row) => ['locale' => $row->locale, 'field' => $row->field, 'value' => $row->value])
                ->all(),
            'metadata' => $this->metas()
                ->orderBy('key')
                ->get()
                ->mapWithKeys(fn ($meta) => [$meta->key => $meta->is_json ? json_decode($meta->value, true) : $meta->value])
                ->all(),
            'files' => $this->files()
                ->orderBy((new \JobMetric\Media\Models\Media())->getTable() . '.id')
                ->get()
                ->map(fn ($file) => ['id' => (int) $file->getKey(), 'collection' => $file->pivot->collection])
                ->all(),
            'taxonomies' => $this->taxonomies()
                ->orderBy((new \JobMetric\Taxonomy\Models\Taxonomy())->getTable() . '.id')
                ->get()
                ->map(fn ($taxonomy) => ['id' => (int) $taxonomy->getKey(), 'collection' => $taxonomy->pivot->collection])
                ->all(),
        ];
    }

    /**
     * Let an aggregate operation record one revision across multiple model saves.
     *
     * @param callable $callback
     *
     * @return mixed
     * @throws \Throwable
     */
    public function withoutRevisionRecording(callable $callback): mixed
    {
        $previous = $this->recordRevisions;
        $this->recordRevisions = false;
        try {
            return $callback();
        } finally {
            $this->recordRevisions = $previous;
        }
    }

    /**
     * Restore owned content and associations while retaining the current workflow status.
     *
     * @param int $revisionId
     *
     * @return void
     * @throws \Throwable
     */
    public function restoreRevision(int $revisionId): void
    {
        $this->getConnection()
            ->transaction(function () use ($revisionId): void {
                $record = static::query()
                    ->lockForUpdate()
                    ->findOrFail($this->getKey());
                $state = (array) $record->revisions()
                    ->findOrFail($revisionId)->snapshot;
                $currentSnapshot = $record->snapshot();
                $record->withoutRevisionRecording(function () use ($record, $state): void {
                    $attributes = (array) ($state['attributes'] ?? []);
                    unset($attributes['status'], $attributes['type']);
                    if (($attributes['slug'] ?? null) === null) {
                        unset($attributes['slug'], $attributes['slug_collection']);
                    }
                    $record->fill($attributes);
                    $translation = [];
                    foreach ((array) ($state['translation'] ?? []) as $row) {
                        $translation[$row['locale']][$row['field']] = $row['value'];
                    }
                    foreach (
                        $record->translations()
                            ->get() as $existing) {
                        if (! array_key_exists($existing->field, $translation[$existing->locale] ?? [])) {
                            $existing->delete();
                        }
                    }
                    foreach ($translation as $locale => $fields) {
                        $record->translations()
                            ->onlyTrashed()
                            ->where('locale', $locale)
                            ->whereIn('field', array_keys($fields))
                            ->restore();
                    }
                    $record->translation = $translation;
                    $record->save();
                    if (($state['attributes']['slug'] ?? null) === null && $record->slug !== null) {
                        $record->forgetSlug();
                    }
                    $record->forgetMetadata();
                    $record->storeMetadataBatch((array) ($state['metadata'] ?? []));
                    $record->files()
                        ->detach();
                    foreach ((array) ($state['files'] ?? []) as $file) {
                        $record->files()
                            ->attach((int) $file['id'], ['collection' => $file['collection'] ?? 'base']);
                    }
                    $record->taxonomies()
                        ->detach();
                    foreach ((array) ($state['taxonomies'] ?? []) as $taxonomy) {
                        $record->taxonomies()
                            ->attach((int) $taxonomy['id'], ['collection' => $taxonomy['collection'] ?? 'taxonomies']);
                    }
                });
                if ($record->snapshot() !== $currentSnapshot) {
                    $record->revisions()
                        ->create(['snapshot' => $currentSnapshot]);
                    app(\JobMetric\EventSystem\Support\CommittedEventDispatcher::class)->dispatch(new \JobMetric\Post\Events\Post\PostRestoreCommittedEvent($record->getKey()), $record->getConnectionName());
                }
                $this->refresh();
            });
    }
}
