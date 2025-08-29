<?php

namespace JobMetric\Post\Tests;

use JobMetric\Post\Enums\PostStatusEnum;
use JobMetric\Post\Facades\Post;
use JobMetric\Post\Http\Resources\PostResource;
use JobMetric\Post\Models\Post as PostModel;
use JobMetric\Taxonomy\Facades\Taxonomy;
use JobMetric\Taxonomy\Models\TaxonomyRelation;
use Throwable;

class PostTest extends BasePost
{

    /**        
     * @throws Throwable
     */
    public function test_store()
    {
        $postAttr = [
            'type' => 'page',
            'comment_status' => true,
            'status' => PostStatusEnum::PENDING(),
            'translation' => [
                'name' => "THIS IS TEST POST NAME",
                'content' => "THIS IS TEST CONTENT"
            ]
        ];
        // store post
        // $post = $this->create_post("page");
        //create a simple
        $post = Post::store($postAttr);
        $this->commonAsserts($post);

        $this->assertDatabaseHas('posts', [
            'type' => 'page',
            'comment_status' => true,
            'status' => PostStatusEnum::PENDING(),
        ]);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'JobMetric\Post\Models\Post',
            'translatable_id' => $post['data']->id,
            'locale' => app()->getLocale(),
            'key' => 'name',
            'value' => 'THIS IS TEST POST NAME',
        ]);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => 'JobMetric\Post\Models\Post',
            'translatable_id' => $post['data']->id,
            'locale' => app()->getLocale(),
            'key' => 'content',
            'value' => 'THIS IS TEST CONTENT',
        ]);

        // $this->assertDatabaseHas('category_paths', [
        //     'type' => 'product_category',
        //     'category_id' => $category['data']->id,
        //     'path_id' => $category['data']->id,
        //     'level' => 0,
        // ]);

        // store duplicate post
        $duplicatePost = Post::store($postAttr);

        $this->assertIsArray($duplicatePost);
        $this->assertFalse($duplicatePost['ok']);
        $this->assertEquals($duplicatePost['message'], trans('post::base.validation.errors'));
        $this->assertEquals(422, $duplicatePost['status']);

        //TODO write tests to test other status and scheduled post to test the scheduler  
    }

    public function store_post_with_a_taxonomy()
    {
        //store post and assign a taxonomy to it
        $taxonomy = Taxonomy::store([
            'type' => 'page_taxonomy',
            'parent_id' => null,
            'ordering' => 1,
            'status' => true,
            'translation' => [
                'name' => 'default page taxonomy',
                'description' => 'taxonomy description',
                'meta_title' => 'default page meta title',
                'meta_description' => 'default page meta description ',
                'meta_keywords' => 'default page meta keywords',
            ],
        ]);

        $post = Post::store([
            'type' => 'page',
            'comment_status' => true,
            'status' => PostStatusEnum::PENDING(),
            'translation' => [
                'name' => "THIS IS TEST POST NAME",
                'content' => "THIS IS TEST CONTENT"
            ],
            'taxonomies' => [
                $taxonomy['data']->id
            ]
        ]);

        $this->commonAsserts($post);

        $this->assertDatabaseHas((new TaxonomyRelation)->getTable(), [
            'taxonomy_id' => app()->getLocale(),
            'taxonomizable_type' => PostModel::class,
            'taxonomizable_id' => $post['data']->id,
            'collection' => 'page',
        ]);
    }


    private function commonAsserts(array $data)
    {
        $this->assertIsArray($data);
        $this->assertTrue($data['ok']);
        $this->assertEquals($data['message'], trans('post::base.messages.created'));
        $this->assertInstanceOf(PostResource::class, $data['data']);
        $this->assertEquals(201, $data['status']);
    }
}
