<?php

namespace JobMetric\Post\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JobMetric\Post\Models\Post;
use JobMetric\Taxonomy\Facades\TaxonomyTypeRegistry;
use JobMetric\Url\Rules\SlugExistRule;

/**
 * Validate package persistence input independently of HTTP and editor drivers.
 */
class PostData
{
    /**
     * Validate a partial aggregate write against its registered capabilities.
     *
     * @param Post $post
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validate(Post $post, array $data): array
    {
        $builder = Post::typeRegistry()->for((string) $post->type);
        $fields = $builder->getTranslationFields();
        $metadata = $builder->getMetadataFields();
        $taxonomies = $post->taxonomyAllowTypes();
        $media = $post->mediaAllowCollections();
        $rules = [
            'status' => ['sometimes', 'string', 'max:80'],
            'published_at' => ['nullable', 'date'],
            'comment_status' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'max:255'],
            'translation' => ['sometimes', 'array'],
            'translation.*' => [in_array('*', $fields, true) ? 'array' : 'array:'.implode(',', $fields)],
            'metadata' => [in_array('*', $metadata, true) ? 'array' : 'array:'.implode(',', $metadata)],
            'taxonomies' => ['sometimes', 'array:'.implode(',', array_keys($taxonomies))],
            'media' => ['sometimes', 'array:'.implode(',', array_keys($media))],
        ];
        if ($builder->hasUrl()) {
            $rules['slug'] = ['sometimes', 'required', 'string', 'max:100', new SlugExistRule(Post::class, (string) $post->type, $post->exists ? (int) $post->id : null)];
        }
        foreach ($builder->getCustomFields() as $key => $definition) {
            $rules['metadata.'.$key] = $definition['rules'] ?? ['nullable', 'string', 'max:255'];
        }
        foreach ($taxonomies as $collection => $definition) {
            $multiple = ($definition['multiple'] ?? true)
                && TaxonomyTypeRegistry::getOption($definition['type'], 'multiple-selection', true);
            $rules['taxonomies.'.$collection] = ['array', ...($multiple ? [] : ['max:1'])];
            $rules['taxonomies.'.$collection.'.*'] = ['integer', 'distinct', Rule::exists(config('taxonomy.tables.taxonomy', 'taxonomies'), 'id')->where('type', $definition['type'])->where('status', true)];
        }
        foreach ($media as $collection => $definition) {
            $rules['media.'.$collection] = ['array', ...(($definition['multiple'] ?? false) ? [] : ['max:1'])];
            $rules['media.'.$collection.'.*'] = ['integer', 'distinct', Rule::exists(config('media.tables.media', 'media'), 'id')->where(function ($query) use ($definition): void {
                $query->where('type', 'f');
                $mimeTypes = (array) ($definition['mimeTypes'] ?? []);
                if ($mimeTypes !== []) {
                    $query->where(function ($query) use ($mimeTypes): void {
                        foreach ($mimeTypes as $mimeType) {
                            $query->orWhere('mime_type', 'like', str_contains($mimeType, '/') ? str_replace('*', '%', $mimeType) : $mimeType.'/%');
                        }
                    });
                }
            })];
        }

        return Validator::make($data, $rules)->validate();
    }
}
