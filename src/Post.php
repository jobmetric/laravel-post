<?php

namespace JobMetric\Post;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use JobMetric\Post\Http\Requests\StorePostRequest;
use JobMetric\Post\Http\Resources\PostResource;
use JobMetric\Post\Models\Post as PostModel;
use JobMetric\Translation\Models\Translation;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

class Post
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected Application $app;

    /**
     * Create a new Setting instance.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get the specified category.
     *
     * @param string $type
     * @param array $filter
     * @param array $with
     *
     * @return QueryBuilder
     * @throws Throwable
     */
    public function query(string $type, array $filter = [], array $with = []): QueryBuilder
    {
        checkTypeInTaxonomyTypes($type);

        $fields = [
            'type',
            'comment_status',
            'password',
            'status',
            'published_at',
            'created_at',
            'updated_at',
            'deleted_at'
        ];
        //TODO complete the query conditions and attribute selections later
        return QueryBuilder::for(PostModel::class);
    }

    /**
     * Paginate the specified categories.
     *
     * @param string $type
     * @param array $filter
     * @param int $page_limit
     * @param array $with
     *
     * @return AnonymousResourceCollection
     * @throws Throwable
     */
    public function paginate(string $type, array $filter = [], int $page_limit = 15, array $with = []): AnonymousResourceCollection
    {
        return PostResource::collection(
            $this->query($type, $filter, $with)->paginate($page_limit)
        );
    }

    /**
     * Get all categories.
     *
     * @param string $type
     * @param array $filter
     * @param array $with
     *
     * @return AnonymousResourceCollection
     * @throws Throwable
     */
    public function all(string $type, array $filter = [], array $with = []): AnonymousResourceCollection
    {
        return PostResource::collection(
            $this->query($type, $filter, $with)->get()
        );
    }

    /**
     * Store the specified category.
     *
     * @param array $data
     * @return array
     * @throws Throwable
     */
    public function store(array $data): array
    {
        $ruleClass = (new StorePostRequest)
            ->setType($data['type'] ?? null)
            ->rules();

        $validator = Validator::make(
            $data,
            $ruleClass
        );

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return [
                'ok' => false,
                'message' => trans('post::base.validation.errors'),
                'errors' => $errors,
                'status' => 422
            ];
        }

        $data = $validator->validated();

        return DB::transaction(function () use ($data) {

            $post = new PostModel;
            $post->type = $data['type'];
            $post->status = $data['status'] ?? true;
            $post->save();

            // if (isset($data['slug'])) {
            //     $category->dispatchUrl($data['slug'], $data['type']);
            // }

            //always we translation array
            foreach ($data['translation'] as $translation_key => $translation_value) {
                $post->translate(app()->getLocale(), [
                    $translation_key => $translation_value
                ]);
            }

            //sometimes we don't have taxonomies
            foreach ($data['taxonomies'] ?? [] as $taxonomyID) {
                $post->attachTaxonomy($taxonomyID, $data['type']);
            }

            // foreach ($data['metadata'] ?? [] as $metadata_key => $metadata_value) {
            //     $category->storeMetadata($metadata_key, $metadata_value);
            // }

            // $mediaAllowCollections = $category->mediaAllowCollections();
            // foreach ($data['media'] ?? [] as $media_key => $media_value) {
            //     if ($mediaAllowCollections[$media_key]['multiple'] ?? false) {
            //         foreach ($media_value as $media_item) {
            //             $category->attachMedia($media_item, $media_key);
            //         }
            //     } else {
            //         $category->attachMedia($media_value, $media_key);
            //     }
            // }

            // event(new CategoryStoreEvent($category, $data, $hierarchical));

            return [
                'ok' => true,
                'message' => trans('post::base.messages.created'),
                'data' => PostResource::make($post),
                'status' => 201
            ];
        });
    }
}
