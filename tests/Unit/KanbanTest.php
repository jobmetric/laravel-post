<?php
namespace JobMetric\Post\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JobMetric\Post\Support\PostTypeRegistry;
use JobMetric\Post\Services\WorkflowTransitionAccess;
use JobMetric\Post\Tasks\RequirePermission;

class KanbanTest extends TestCase
{
    public function test_kanban_is_explicit_and_array_configuration_matches_chain(): void
    {
        $builder = (new PostTypeRegistry)->register('kanban-test');
        self::assertFalse($builder->hasKanban());
        self::assertTrue($builder->kanban()->hasKanban());
        self::assertFalse($builder->apply(['kanban' => false])->hasKanban());
    }

    public function test_multiple_boards_are_opt_in_and_chain_order_is_explicit(): void
    {
        $builder = (new PostTypeRegistry)->register('multi-test');
        self::assertFalse($builder->hasMultipleKanban());
        self::assertTrue($builder->multipleKanban()->hasKanban());
        self::assertTrue($builder->hasMultipleKanban());
        self::assertFalse($builder->multipleKanban(false)->hasMultipleKanban());
        self::assertTrue($builder->hasKanban());
        self::assertFalse($builder->multipleKanban()->kanban(false)->hasMultipleKanban());
        self::assertFalse($builder->hasKanban());
        self::assertTrue($builder->kanban()->multipleKanban()->hasMultipleKanban());
        foreach ([['kanban'=>false,'multiple_kanban'=>true], ['multiple_kanban'=>true,'kanban'=>false]] as $options) {
            self::assertTrue($builder->apply($options)->hasMultipleKanban());
        }
        $this->expectException(\InvalidArgumentException::class);
        $builder->apply(['multiple_kanban'=>'true']);
    }

    public function test_single_role_task_migrates_and_ambiguous_restrictions_survive(): void
    {
        $task = ['driver' => RequirePermission::class, 'config' => ['roles' => [7]], 'status' => true];
        $access = new WorkflowTransitionAccess;
        $graph = $access->normalize(['transitions' => [['tasks' => [$task]]]]);
        self::assertSame([7], $graph['transitions'][0]['roles']);
        self::assertSame([], $graph['transitions'][0]['tasks']);
        $graph = ['transitions' => [['tasks' => [$task, $task]]]];
        self::assertSame($graph, $access->normalize($graph));
        $task['config'] = ['permission' => 'admin.content'];
        $graph = ['transitions' => [['tasks' => [$task]]]];
        self::assertSame($graph, $access->normalize($graph));
    }
    public function test_malformed_graphs_are_left_for_validation_without_unsafe_normalization(): void
    {
        $access = new WorkflowTransitionAccess;
        foreach ([['transitions' => 'bad'], ['transitions' => ['bad']], ['transitions' => [['tasks' => 'bad']]],
            ['transitions' => [['tasks' => [null, ['driver' => RequirePermission::class, 'config' => 'bad']]]]]] as $graph) {
            self::assertSame($graph, $access->normalize($graph));
        }
    }

}
