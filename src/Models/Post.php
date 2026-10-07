<?php

namespace JobMetric\Post\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use JobMetric\Flow\HasWorkflow;
use JobMetric\Media\Contracts\MediaContract;
use JobMetric\Media\HasFile;
use JobMetric\Metadata\HasMeta;
use JobMetric\Post\Contracts\PostContract;
use JobMetric\Post\Support\PostTypeRegistry;
use JobMetric\Taxonomy\Contracts\TaxonomyContract;
use JobMetric\Taxonomy\Facades\TaxonomyTypeRegistry;
use JobMetric\Taxonomy\HasTaxonomy;
use JobMetric\Translation\HasTranslation;
use JobMetric\Url\Contracts\UrlContract;
use JobMetric\Url\HasUrl;

class Post extends Model implements MediaContract, PostContract, TaxonomyContract, UrlContract
{
    use HasFactory, SoftDeletes, HasWorkflow, HasTranslation, HasMeta, HasFile, HasTaxonomy, HasUrl {
        HasTranslation::translate as protected persistTranslation;
        HasMeta::storeMetadata as protected persistMetadata;
    }

    protected $fillable = ['type', 'comment_status', 'password', 'status', 'published_at', 'translation', 'metadata', 'slug', 'slug_collection'];
    protected $casts = ['comment_status' => 'boolean', 'published_at' => 'datetime'];
    protected array $translatables = ['*'];
    protected array $metadata = [];

    private ?array $revisionState = null;

    public static function typeRegistry(): PostTypeRegistry { return app(PostTypeRegistry::class); }
    protected static function newFactory(): Factory { return \JobMetric\Post\Factories\PostFactory::new(); }
    public function getTable(): string { return config('post.tables.post', 'posts'); }
    public function getRevisionTable(): string { return config('post.tables.revision', 'post_revisions'); }

    public function taxonomyAllowTypes(): array
    {
        $types = (array) static::typeRegistry()->getOption((string) $this->type, 'taxonomy-types', []);

        foreach ($types as $collection => $definition) {
            $taxonomyType = (string) ($definition['type'] ?? '');
            if ($taxonomyType === '' || ! TaxonomyTypeRegistry::has($taxonomyType)) {
                throw new \InvalidArgumentException("Taxonomy type [{$taxonomyType}] in post collection [{$collection}] is not registered.");
            }
        }

        return $types;
    }

    public function translationAllowFields(): array
    {
        return (array) static::typeRegistry()->getOption((string) $this->type, 'translation-fields', ['title', 'excerpt', 'content']);
    }

    public function translate(string $locale, array $data): static
    {
        $allowed = $this->translationAllowFields();
        $disallowed = array_filter(array_keys($data), fn (string $field): bool => ! in_array('*', $allowed, true) && ! in_array($field, $allowed, true));
        if ($disallowed !== []) {
            throw new \InvalidArgumentException('Translation fields are not allowed for this post type: '.implode(', ', $disallowed));
        }
        return $this->persistTranslation($locale, $data);
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'translation' && is_array($value)) {
            $type = (string) ($this->attributes['type'] ?? '');
            if ($type !== '' && static::typeRegistry()->has($type)) {
                $allowed = $this->translationAllowFields();
                foreach ($value as $locale => $fields) {
                    if (! is_array($fields)) {
                        throw new \InvalidArgumentException("Translation payload for locale [{$locale}] must be an array.");
                    }

                    $disallowed = array_filter(array_keys($fields), fn (string $field): bool => ! in_array('*', $allowed, true) && ! in_array($field, $allowed, true));
                    if ($disallowed !== []) {
                        throw new \InvalidArgumentException('Translation fields are not allowed for this post type: '.implode(', ', $disallowed));
                    }
                }
            }
        }

        if ($key === 'metadata' && is_array($value)) {
            $type = (string) ($this->attributes['type'] ?? '');
            if ($type !== '' && static::typeRegistry()->has($type)) {
                $allowed = (array) static::typeRegistry()->getOption($type, 'metadata-fields', []);
                $disallowed = array_filter(array_keys($value), fn (string $field): bool => ! in_array('*', $allowed, true) && ! in_array($field, $allowed, true));
                if ($disallowed !== []) {
                    throw new \InvalidArgumentException('Metadata fields are not allowed for this post type: '.implode(', ', $disallowed));
                }
            }
        }

        return parent::setAttribute($key, $value);
    }

    public function metadataAllowFields(): array
    {
        return (array) static::typeRegistry()->getOption((string) $this->type, 'metadata-fields', []);
    }

    public function storeMetadata(string $key, array|string|bool|null $value = null): static
    {
        $allowed = (array) static::typeRegistry()->getOption((string) $this->type, 'metadata-fields', []);
        if (! in_array('*', $allowed, true) && ! in_array($key, $allowed, true)) {
            throw new \InvalidArgumentException("Metadata field [{$key}] is not allowed for post type [{$this->type}].");
        }
        return $this->persistMetadata($key, $value);
    }

    public function mediaAllowCollections(): array
    {
        $collections = (array) static::typeRegistry()->getOption((string) $this->type, 'media-collections', []);
        $collections['base'] ??= ['media_collection' => 'public', 'size' => []];
        foreach ($collections as &$definition) {
            $definition['media_collection'] = $definition['mediaCollection'] ?? $definition['media_collection'] ?? 'public';
            $definition['size'] ??= [];
        }
        return $collections;
    }

    public function getFullUrl(): string
    {
        $prefix = trim((string) static::typeRegistry()->getOption((string) $this->type, 'url-prefix', ''), '/');
        $slug = trim((string) ($this->slug ?? ''), '/');
        return trim($prefix.'/'.$slug, '/');
    }

    protected function flowSubjectCollection(): ?string
    {
        return (string) $this->type;
    }

    public function revisions(): HasMany { return $this->hasMany(PostRevision::class, 'post_id')->latest('id'); }

    public function scopeOfType(Builder $query, string $type): Builder { return $query->where('type', $type); }
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'publish')->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public static function booted(): void
    {
        static::saving(function (self $post): void {
            $type = (string) $post->type;
            static::typeRegistry()->ensureExists($type);
            $allowedTranslations = (array) static::typeRegistry()->getOption($type, 'translation-fields', ['title', 'excerpt', 'content']);
            foreach ($post->innerTranslations as $locale => $fields) {
                $disallowed = array_filter(array_keys($fields), fn (string $field): bool => ! in_array('*', $allowedTranslations, true) && ! in_array($field, $allowedTranslations, true));
                if ($disallowed !== []) {
                    throw new \InvalidArgumentException('Translation fields are not allowed for this post type: '.implode(', ', $disallowed));
                }
            }

            $allowed = (array) static::typeRegistry()->getOption($type, 'metadata-fields', []);
            $metadata = $post->innerMeta;
            $disallowed = array_filter(array_keys($metadata), fn (string $field): bool => ! in_array('*', $allowed, true) && ! in_array($field, $allowed, true));
            if ($disallowed !== []) {
                throw new \InvalidArgumentException('Metadata fields are not allowed for this post type: '.implode(', ', $disallowed));
            }
        });

        static::updating(function (self $post): void { $post->revisionState = $post->snapshot(); });
        static::updated(function (self $post): void {
            if ($post->revisionState !== null) {
                $post->revisions()->create(['snapshot' => $post->revisionState]);
                $post->revisionState = null;
            }
        });
        static::forceDeleted(function (self $post): void { $post->files()->detach(); });
    }

    public function snapshot(): array
    {
        return [
            'attributes' => $this->only(['type', 'comment_status', 'password', 'status', 'published_at', 'slug', 'slug_collection']),
            'translation' => $this->translations()->orderBy('locale')->orderBy('field')->get(['locale', 'field', 'value'])->map(fn ($row) => ['locale' => $row->locale, 'field' => $row->field, 'value' => $row->value])->all(),
            'metadata' => $this->metas()->orderBy('key')->get()->mapWithKeys(fn ($meta) => [$meta->key => $meta->is_json ? json_decode($meta->value, true) : $meta->value])->all(),
            'files' => $this->files()->orderBy((new \JobMetric\Media\Models\Media)->getTable().'.id')->get()->map(fn ($file) => ['id' => (int) $file->getKey(), 'collection' => $file->pivot->collection])->all(),
            'taxonomies' => $this->taxonomies()->orderBy((new \JobMetric\Taxonomy\Models\Taxonomy)->getTable().'.id')->get()->map(fn ($taxonomy) => ['id' => (int) $taxonomy->getKey(), 'collection' => $taxonomy->pivot->collection])->all(),
        ];
    }

    public function restoreRevision(int $revisionId): void
    {
        $revision = $this->revisions()->findOrFail($revisionId);
        $state = (array) $revision->snapshot;
        DB::transaction(function () use ($state): void {
            $attributes = (array) ($state['attributes'] ?? []);
            unset($attributes['status']);
            $this->fill($attributes);
            $translation = [];
            foreach ((array) ($state['translation'] ?? []) as $row) { $translation[$row['locale']][$row['field']] = $row['value']; }
            $this->translation = $translation;
            $this->save();
            $this->forgetMetadata();
            $this->storeMetadataBatch((array) ($state['metadata'] ?? []));
            foreach ($this->files()->get() as $file) { $this->files()->detach($file->getKey()); }
            foreach ((array) ($state['files'] ?? []) as $file) {
                $this->files()->attach((int) $file['id'], ['collection' => $file['collection'] ?? 'base']);
            }
            foreach ($this->taxonomies()->get() as $taxonomy) { $this->taxonomies()->detach($taxonomy->getKey()); }
            foreach ((array) ($state['taxonomies'] ?? []) as $taxonomy) {
                $this->taxonomies()->attach((int) $taxonomy['id'], ['collection' => $taxonomy['collection'] ?? 'taxonomies']);
            }
        });
    }
}
