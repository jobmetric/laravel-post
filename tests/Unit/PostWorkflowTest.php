<?php

namespace JobMetric\Post\Tests\Unit;

require_once __DIR__.'/../../src/Exceptions/PostStatusTransitionNotAllowedException.php';
require_once __DIR__.'/../../src/Services/PostWorkflow.php';

use Illuminate\Container\Container;
use JobMetric\Flow\Models\Flow;
use JobMetric\Post\Exceptions\PostStatusTransitionNotAllowedException;
use JobMetric\Post\Models\Post;
use JobMetric\Post\Services\PostWorkflow;
use JobMetric\Post\Support\PostTypeRegistry;
use PHPUnit\Framework\TestCase;

class PostWorkflowTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_statuses_are_empty_when_post_has_no_bound_or_default_flow(): void
    {
        $post = new PostWithoutWorkflow;

        self::assertSame([], (new PostWorkflow)->availableStatuses($post));
        self::assertSame([], (new PostWorkflow)->allStatusOptions($post, 'en'));
    }

    public function test_missing_configured_workflow_rejects_status_transitions(): void
    {
        $registry = new PostTypeRegistry;
        $registry->register('article')->workflow('article');
        $container = new Container;
        $container->instance(PostTypeRegistry::class, $registry);
        Container::setInstance($container);

        $post = new PostWithoutWorkflow;
        $post->type = 'article';

        $this->expectException(PostStatusTransitionNotAllowedException::class);
        (new PostWorkflow)->transition($post, 'publish', null);
    }
}

class PostWithoutWorkflow extends Post
{
    public function boundFlow(): ?Flow
    {
        return null;
    }

    public function pickFlow(): ?Flow
    {
        return null;
    }
}
