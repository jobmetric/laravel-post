<?php

namespace JobMetric\Post\Tests\Unit;

require_once __DIR__.'/../../src/Support/PostTypeRegistry.php';
require_once __DIR__.'/../../src/Support/PostTypeBuilder.php';

use JobMetric\Post\Support\PostTypeRegistry;
use JobMetric\Post\Models\Post;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class PostTypeRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_post_type_builder_registers_capabilities(): void
    {
        $registry = new PostTypeRegistry;
        $type = $registry->register('article')
            ->label('Articles')
            ->translationFields(['title', 'content'])
            ->allowTaxonomy('category', 'categories', true)
            ->mediaCollection('gallery', true, ['image'])
            ->urlPrefix('articles')
            ->workflow('article')
            ->get();

        self::assertSame(['title', 'content'], $type['translation-fields']);
        self::assertSame(['type' => 'category', 'multiple' => true], $type['taxonomy-types']['categories']);
        self::assertTrue($type['media-collections']['gallery']['multiple']);
        self::assertSame('articles', $type['url-prefix']);
        self::assertSame('article', $type['workflow']);
    }

    public function test_administration_permissions_use_explicit_taxonomy_style_chains(): void
    {
        $builder = (new PostTypeRegistry)->register('article')
            ->viewPermission('admin.articles.view')
            ->managePermission('admin.articles.manage');

        self::assertSame('admin.articles.view', $builder->getViewPermission());
        self::assertSame('admin.articles.manage', $builder->getManagePermission());
    }

    public function test_invalid_administration_permission_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PostTypeRegistry)->register('article')->viewPermission('Admin Articles');
    }

    public function test_registry_applies_and_validates_permission_options(): void
    {
        $registry = new PostTypeRegistry;
        $builder = $registry->register('article', [
            'view-permission' => 'admin.articles.view',
            'manage-permission' => 'admin.articles.manage',
        ]);

        self::assertSame('admin.articles.view', $builder->getViewPermission());
        self::assertSame('admin.articles.manage', $builder->getManagePermission());

        try {
            $registry->register('invalid', ['view-permission' => 'invalid permission']);
            self::fail('Invalid permissions should prevent type registration.');
        } catch (\InvalidArgumentException) {
            self::assertFalse($registry->has('invalid'));
        }
    }

    public function test_url_capability_matches_the_taxonomy_builder_contract(): void
    {
        $builder = (new PostTypeRegistry)->register('article')
            ->url()
            ->urlPrefix('articles/news');

        self::assertTrue($builder->hasUrl());
        self::assertSame('articles/news', $builder->getUrlPrefix());
    }

    public function test_invalid_post_type_key_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PostTypeRegistry)->register('Invalid Type');
    }

    public function test_list_filters_can_be_registered_fluently(): void
    {
        $type = (new PostTypeRegistry)->register('article')
            ->allowTaxonomy('category', 'categories')
            ->filterTaxonomy('categories')
            ->addFilter('author', ['label' => 'Author', 'options' => ['1' => 'Alice'], 'apply' => static function (): void {}])
            ->get();

        self::assertTrue($type['taxonomy-types']['categories']['filter']);
        self::assertSame('Author', $type['filters']['author']['label']);
    }

    public function test_post_model_enforces_the_registered_type_field_allowlists(): void
    {
        $registry = new PostTypeRegistry;
        $registry->register('article')->metadataFields(['subtitle']);
        $container = new Container;
        $container->instance(PostTypeRegistry::class, $registry);
        Container::setInstance($container);

        $post = new Post;
        $post->type = 'article';
        self::assertSame(['subtitle'], $post->metadataAllowFields());
        $post->metadata = ['subtitle' => 'A short deck'];

        $this->expectException(\InvalidArgumentException::class);
        $post->metadata = ['unregistered' => 'Rejected'];
    }
}
