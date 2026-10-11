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
            ->taxonomy('category', 'categories', true)
            ->mediaCollection('gallery', true, ['image'])
            ->url('articles')
            ->workflow('article')
            ->get();

        self::assertSame(['title', 'content'], $type['translation-fields']);
        self::assertSame(['type' => 'category', 'multiple' => true], $type['taxonomy-types']['categories']);
        self::assertTrue($type['media-collections']['gallery']['multiple']);
        self::assertTrue($type['has-url']);
        self::assertSame('articles', $type['url-prefix']);
        self::assertSame('article', $type['workflow']);
    }

    public function test_has_seo_registers_translations_metadata_and_sharing_image(): void
    {
        $builder = (new PostTypeRegistry)->register('article')
            ->translationFields(['title', 'content'])
            ->hasSeo();

        self::assertSame(
            ['title', 'content', 'meta_title', 'meta_description', 'meta_keywords'],
            $builder->getTranslationFields()
        );
        self::assertSame(['multiple' => false, 'mimeTypes' => ['image']], $builder->getMediaCollections()['seo_image']);
        self::assertContains('seo_index', $builder->getMetadataFields());

        $builder->hasSeo(false)->hasSeo();
        self::assertSame(['title', 'content', 'meta_title', 'meta_description', 'meta_keywords'], $builder->getTranslationFields());
        self::assertSame(['seo_image'], array_keys($builder->getMediaCollections()));
    }

    public function test_seo_chain_alias_enables_the_same_capabilities(): void
    {
        $builder = (new PostTypeRegistry)->register('article')->seo();

        self::assertTrue($builder->isSeoEnabled());
        self::assertContains('meta_title', $builder->getTranslationFields());
        self::assertContains('seo_index', $builder->getMetadataFields());
        self::assertArrayHasKey('seo_image', $builder->getMediaCollections());
    }

    public function test_administration_permissions_use_explicit_taxonomy_style_chains(): void
    {
        $builder = (new PostTypeRegistry)->register('article')
            ->viewPermission('admin.articles.view')
            ->managePermission('admin.articles.manage');

        self::assertSame('admin.articles.view', $builder->getViewPermission());
        self::assertSame('admin.articles.manage', $builder->getManagePermission());
    }

    public function test_base_permission_derives_view_and_manage_permissions(): void
    {
        $builder = (new PostTypeRegistry)->register('article')
            ->permission('admin.articles');

        self::assertSame('admin.articles', $builder->getPermission());
        self::assertSame('admin.articles.view', $builder->getViewPermission());
        self::assertSame('admin.articles.manage', $builder->getManagePermission());
    }

    public function test_specific_administration_permissions_override_the_base_permission(): void
    {
        $builder = (new PostTypeRegistry)->register('article')
            ->permission('admin.articles')
            ->viewPermission('admin.content.read')
            ->managePermission('admin.content.write');

        self::assertSame('admin.articles', $builder->getPermission());
        self::assertSame('admin.content.read', $builder->getViewPermission());
        self::assertSame('admin.content.write', $builder->getManagePermission());
    }

    public function test_registry_applies_the_base_permission_chain(): void
    {
        $builder = (new PostTypeRegistry)->register('article', ['permission' => 'admin.articles']);

        self::assertSame('admin.articles.view', $builder->getViewPermission());
        self::assertSame('admin.articles.manage', $builder->getManagePermission());
    }

    public function test_registry_applies_declared_list_filter_fields(): void
    {
        $builder = (new PostTypeRegistry)->register('article', [
            'filters' => ['status', 'taxonomy.category', 'metadata.featured'],
        ]);

        self::assertSame(['status', 'taxonomy.category', 'metadata.featured'], $builder->getFilterFields());
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

    public function test_url_chain_enables_url_and_sets_the_prefix(): void
    {
        $builder = (new PostTypeRegistry)->register('article')
            ->url('articles/news');

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
            ->taxonomy('category', 'categories')
            ->filters(['status', 'taxonomy.category', 'metadata.featured'])
            ->addFilter('author', ['label' => 'Author', 'options' => ['1' => 'Alice'], 'apply' => static function (): void {}])
            ->get();

        self::assertSame(['type' => 'category', 'multiple' => true], $type['taxonomy-types']['categories']);
        self::assertSame(['status', 'taxonomy.category', 'metadata.featured'], $type['filter-fields']);
        self::assertSame('Author', $type['filters']['author']['label']);
    }

    public function test_taxonomy_style_administration_chains_are_registered(): void
    {
        $builder = (new PostTypeRegistry)->register('article')
            ->backUrl('/admin/content')
            ->optimisticLocking()
            ->help()
            ->baseMedia()
            ->changeStatusInList();

        self::assertSame('/admin/content', $builder->getBackUrl());
        self::assertTrue($builder->hasOptimisticLocking());
        self::assertTrue($builder->hasHelp());
        self::assertTrue($builder->hasBaseMedia());
        self::assertSame(['multiple' => false, 'mimeTypes' => ['image']], $builder->getMediaCollections()['base']);
        self::assertTrue($builder->hasChangeStatusInList());
    }

    public function test_list_status_changes_are_disabled_by_default(): void
    {
        $builder = (new PostTypeRegistry)->register('article');

        self::assertFalse($builder->hasChangeStatusInList());
        self::assertFalse($builder->hasHelp());
        self::assertFalse($builder->hasOptimisticLocking());
        self::assertFalse($builder->hasBaseMedia());
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
