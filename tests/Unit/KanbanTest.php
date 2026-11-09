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
}
