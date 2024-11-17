<?php

namespace JobMetric\Post\Tests;

use JobMetric\Post\Models\Post as PostModel;
use JobMetric\Taxonomy\Facades\Taxonomy;
use Tests\BaseDatabaseTestCase as BaseTestCase;
class BasePost extends BaseTestCase
{
    /**
     * create a fake product
     *
     * @return PostModel
     */
    public function create_post(string $type): PostModel
    {
        return PostModel::factory()->create([
            'type' => $type
        ]);
    }


    public function create_post_taxonomy(string $type, string $parent_id = null)
    {
        return Taxonomy::store([
            'type' => $type,
            'parent_id' => $parent_id,
            'ordering' => 1,
            'status' => true,
            'translation' => [
                'name' => 'electro',
                'description' => 'this is electro test taxonomy',
                'meta_title' => 'this is meta title electro test taxonomy',
                'meta_description' => 'this is meta description electro test taxonomy',
                'meta_keywords' => 'this is meta key electro test taxonomy',
            ],
        ]);
    }


    // public function create_post_taxonomy()
    // {

    // }

}
