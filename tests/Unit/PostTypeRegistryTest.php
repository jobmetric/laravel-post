<?php

namespace JobMetric\Post\Tests\Unit;

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
