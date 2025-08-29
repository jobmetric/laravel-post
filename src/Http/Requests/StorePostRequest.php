<?php

namespace JobMetric\Post\Http\Requests;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JobMetric\Media\Http\Requests\MediaTypeObjectRequest;
use JobMetric\Metadata\Http\Requests\MetadataTypeObjectRequest;
use JobMetric\Post\Enums\PostStatusEnum;
use JobMetric\Post\Models\Post;
use JobMetric\Taxonomy\Rules\TaxonomyExistRule;
use JobMetric\Translation\Http\Requests\TranslationTypeObjectRequest;
use JobMetric\Url\Http\Requests\UrlTypeObjectRequest;

class StorePostRequest extends FormRequest
{
    use TranslationTypeObjectRequest, MetadataTypeObjectRequest, MediaTypeObjectRequest, UrlTypeObjectRequest;

    public string|null $type = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {

        if (is_null($this->type)) {
            $type = $this->input('type');
        } else {
            $type = $this->type;
        }

        $postTypes = getPostTypes();
        $rules = [
            'type' => [
                'required',
                'string',
                Rule::in(getPostTypes('key'))
            ],
            'comment_status' => ['sometimes', 'boolean'],
            'status' => [
                'required',
                Rule::in(PostStatusEnum::values())
            ],
            'password' => ['sometimes', 'string'],
            'published_at' => ['sometimes', 'date'],
            'taxonomies' => [
                'sometimes',
                'array',
                new TaxonomyExistRule()
            ],
        ];

        // I passed the name for check it's exists before or not
        $this->renderTranslationFiled(
            $rules,
            $postTypes[$type],
            Post::class,
            'name'
        );

        return $rules;
    }

    /**
     * Set type for validation
     *
     * @param string $type
     * @return static
     */
    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->status ?? PostStatusEnum::PENDING(),
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        $type = $this->type ?? $this->input('type');
        $params = [
            'comment_status' => trans('post::base.')
        ];
        // $params = [
        //     'parent_id' => trans('taxonomy::base.form.fields.parent.title'),
        //     'ordering' => trans('taxonomy::base.form.fields.ordering.title'),
        //     'status' => trans('package-core::base.components.boolean_status.label'),
        //     'translation.name' => trans('translation::base.fields.name.label'),
        // ];

        // $taxonomyTypes = getTaxonomyType(type: $type);

        // if (isset($taxonomyTypes['translation'])) {
        //     if (isset($taxonomyTypes['translation']['fields'])) {
        //         foreach ($taxonomyTypes['translation']['fields'] as $field_key => $field_value) {
        //             $params['translation.' . $field_key] = trans($field_value['label']);
        //         }
        //     }
        //     if (isset($taxonomyTypes['translation']['seo']) && $taxonomyTypes['translation']['seo']) {
        //         $params['translation.meta_title'] = trans('translation::base.fields.meta_title.label');
        //         $params['translation.meta_description'] = trans('translation::base.fields.meta_description.label');
        //         $params['translation.meta_keywords'] = trans('translation::base.fields.meta_keywords.label');
        //     }
        // }

        // if (isset($taxonomyTypes['metadata'])) {
        //     foreach ($taxonomyTypes['metadata'] as $field_key => $field_value) {
        //         $params['metadata.' . $field_key] = trans($field_value['label']);
        //     }
        // }

        // if (isset($taxonomyTypes['has_url']) && $taxonomyTypes['has_url']) {
        //     $params['slug'] = trans('url::base.components.url_slug.title');
        // }

        // if (isset($taxonomyTypes['has_base_media']) && $taxonomyTypes['has_base_media']) {
        //     $params['media.base'] = trans('taxonomy::base.form.media.base.title');
        // }

        // if (isset($taxonomyTypes['media'])) {
        //     foreach ($taxonomyTypes['media'] as $media_collection => $media_item) {
        //         $params['media.' . $media_collection] = trans('taxonomy::base.form.media.' . $media_collection . '.title');
        //     }
        // }
        //TODO write the correct custom error translation later
        return ['nothing As attr right now'];
    }
}
