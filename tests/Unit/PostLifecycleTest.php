<?php

namespace JobMetric\Post\Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use JobMetric\Post\Enums\ScheduledEditPolicy;
use JobMetric\Post\Models\Post;
use JobMetric\Post\Models\PostContentRevision;
use JobMetric\Post\Models\PostPublicationStream;
use JobMetric\Post\Services\PostLifecycle;
use JobMetric\Post\Services\PostWorkflowDefinitions;
use JobMetric\Post\Support\PostTypeRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Exercise publication invariants against real SQLite transactions. */
class PostLifecycleTest extends TestCase
{
    private Manager $database;
    private Post $post;
    private PostLifecycle $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();
        $app = new Container;
        Container::setInstance($app);
        $app->instance('config', new Repository(['database' => ['default' => 'default'], 'post' => ['tables' => ['post' => 'posts']], 'workflow' => ['tables' => ['flow_use' => 'flow_uses']]]));
        $app->instance('events', new Dispatcher($app));
        $app->instance(\Illuminate\Contracts\Events\Dispatcher::class, $app['events']);
        $app->instance(\Psr\Log\LoggerInterface::class, new \Psr\Log\NullLogger);
        $app->instance('db.transactions', new \Illuminate\Database\DatabaseTransactionsManager);
        $app->instance('translator', new Translator(new ArrayLoader, 'en'));
        $app->instance('validator', new Factory($app['translator'], $app));
        $this->database = new Manager($app);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->database->setEventDispatcher($app['events']);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $app->instance('db', $this->database->getDatabaseManager());
        $app->instance(\Illuminate\Database\DatabaseManager::class, $app['db']);
        $app->bind('db.schema', fn () => $this->database->schema());
        Facade::setFacadeApplication($app);
        $this->database->schema()->create('posts', function ($table): void {
            $table->id(); $table->string('type'); $table->string('status'); $table->softDeletes(); $table->timestamps();
        });
        $this->database->schema()->create('flow_uses', function ($table): void {
            $table->id(); $table->string('flowable_type'); $table->unsignedBigInteger('flowable_id');
            $table->unsignedBigInteger('flow_id'); $table->timestamp('used_at')->nullable(); $table->timestamps();
        });
        $this->database->schema()->create('flows', function ($table): void {
            $table->id(); $table->string('subject_type'); $table->string('subject_collection')->nullable();
            $table->unsignedInteger('version'); $table->boolean('status'); $table->boolean('is_default'); $table->timestamps();
            $table->softDeletes();
        });
        $this->database->schema()->create('flow_states', function ($table): void {
            $table->id(); $table->unsignedBigInteger('flow_id'); $table->string('status');
            $table->string('type'); $table->json('config')->nullable(); $table->timestamps();
        });
        $this->database->schema()->create('flow_transitions', function ($table): void {
            $table->id(); $table->unsignedBigInteger('flow_id'); $table->unsignedBigInteger('from')->nullable();
            $table->unsignedBigInteger('to')->nullable(); $table->string('slug'); $table->timestamps();
        });
        foreach (glob(__DIR__.'/../../database/migrations/2026_10_13_*.php') as $migration) {
            (require $migration)->up();
        }
        $registry = new PostTypeRegistry;
        $registry->register('post');
        $app->instance(PostTypeRegistry::class, $registry);
        $app->instance(\JobMetric\Post\Support\ScheduledPostActionRegistry::class,
            (new \JobMetric\Post\Support\ScheduledPostActionRegistry)
                ->register(new \JobMetric\Post\Actions\PublishPostRevision));
        $id = $this->database->table('posts')->insertGetId(['type' => 'post', 'status' => 'draft']);
        $this->post = Post::findOrFail($id);
        $this->lifecycle = new PostLifecycle;
        Carbon::setTestNow('2026-10-13 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Post::clearBootedModels();
        parent::tearDown();
    }

    public function test_new_aggregate_draft_never_changes_the_live_languages(): void
    {
        $fa = $this->lifecycle->saveDraft($this->post, '*', ['languages' => ['fa' => ['title' => 'first']], 'shared' => []]);
        $this->lifecycle->publish($this->post, '*', $fa->draft_revision_id);
        $this->lifecycle->saveDraft($this->post, '*', ['languages' => ['fa' => ['title' => 'changed'], 'en' => ['title' => 'English']], 'shared' => []]);
        self::assertSame('first', $this->lifecycle->published($this->post, 'fa')['content']['title']);
        self::assertNull($this->lifecycle->published($this->post, 'en'));
        self::assertSame([$this->post->id], Post::query()->liveInLocale('fa')->pluck('id')->all());
        self::assertSame([], Post::query()->liveInLocale('en')->pluck('id')->all());
    }

    public function test_preserve_policy_publishes_exact_approved_revision_once(): void
    {
        $stream = $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'approved']);
        $this->lifecycle->approve($this->post, 'fa', $stream->draft_revision_id);
        $schedule = $this->lifecycle->schedule($this->post, 'fa', $stream->draft_revision_id, now()->addHours(2));
        self::assertSame(3, $stream->refresh()->version);
        $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'new draft']);
        self::assertFalse($this->lifecycle->execute($schedule->id));
        Carbon::setTestNow('2026-10-13 12:00:00');
        self::assertTrue($this->lifecycle->execute($schedule->id));
        self::assertFalse($this->lifecycle->execute($schedule->id));
        self::assertSame('approved', $this->lifecycle->published($this->post, 'fa')['content']['title']);
        $completion = $this->database->table('post_lifecycle_events')
            ->where('event', 'scheduled_action_completed')->first();
        self::assertSame((int) $schedule->revision_id, (int) $completion->revision_id);
    }

    public function test_due_action_of_a_deleted_post_is_cancelled_without_publication(): void
    {
        $stream = $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'Never send']);
        $this->lifecycle->approve($this->post, 'fa', (int) $stream->draft_revision_id);
        $schedule = $this->lifecycle->schedule($this->post, 'fa', (int) $stream->draft_revision_id, now()->addHour());
        $this->database->table('posts')->where('id', $this->post->id)->update(['deleted_at' => now()]);
        Carbon::setTestNow('2026-10-13 11:00:00');

        self::assertFalse($this->lifecycle->execute((int) $schedule->id));
        self::assertSame('cancelled', $schedule->refresh()->state);
        self::assertNull($stream->refresh()->live_revision_id);
    }

    public function test_registered_scheduled_action_receives_only_the_approved_revision_once(): void
    {
        app(\JobMetric\Post\Support\ScheduledPostActionRegistry::class)->register(new class implements \JobMetric\Post\Contracts\ScheduledPostAction {
            public function key(): string
            {
                return 'record_delivery';
            }

            public function execute(\JobMetric\Post\Models\PostPublicationStream $stream, \JobMetric\Post\Models\PostContentRevision $revision, \JobMetric\Post\Models\PostScheduledAction $schedule): void
            {
                app(PostLifecycle::class)->record($stream, 'delivery_requested', null,
                    ['schedule_id' => $schedule->id], (int) $revision->id);
            }
        });
        $stream = $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'Approved']);
        $this->lifecycle->approve($this->post, 'fa', (int) $stream->draft_revision_id);
        $schedule = $this->lifecycle->schedule($this->post, 'fa', (int) $stream->draft_revision_id,
            now()->addHour(), action: 'record_delivery');
        $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'Later draft']);
        Carbon::setTestNow('2026-10-13 11:00:00');

        self::assertTrue($this->lifecycle->execute((int) $schedule->id));
        self::assertFalse($this->lifecycle->execute((int) $schedule->id));
        self::assertNull($stream->refresh()->live_revision_id);
        self::assertSame(1, $this->database->table('post_lifecycle_events')
            ->where('event', 'delivery_requested')->where('revision_id', $schedule->revision_id)->count());
    }

    public function test_sensitive_edit_pauses_and_approval_does_not_resume(): void
    {
        app(PostTypeRegistry::class)->for('post')->scheduledEditPolicy(ScheduledEditPolicy::Pause);
        $stream = $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'letter']);
        $this->lifecycle->approve($this->post, 'fa', $stream->draft_revision_id);
        $schedule = $this->lifecycle->schedule($this->post, 'fa', $stream->draft_revision_id, now()->addHours(2));
        $changed = $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'corrected letter']);
        $this->lifecycle->approve($this->post, 'fa', $changed->draft_revision_id);
        self::assertSame('paused', $schedule->refresh()->state);
        Carbon::setTestNow('2026-10-13 12:00:00');
        self::assertFalse($this->lifecycle->execute($schedule->id));
        $this->expectException(ValidationException::class);
        $this->lifecycle->schedule($this->post, 'fa', $changed->draft_revision_id, now(), null, $schedule->id);
    }

    public function test_explicit_resume_uses_reapproved_current_revision(): void
    {
        app(PostTypeRegistry::class)->for('post')->scheduledEditPolicy(ScheduledEditPolicy::Pause);
        $stream = $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'old']);
        $this->lifecycle->approve($this->post, 'fa', $stream->draft_revision_id);
        $schedule = $this->lifecycle->schedule($this->post, 'fa', $stream->draft_revision_id, now()->addHours(2));
        $stream = $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'new']);
        $this->lifecycle->approve($this->post, 'fa', $stream->draft_revision_id);
        $this->lifecycle->schedule($this->post, 'fa', $stream->draft_revision_id, now()->addHours(3), null, $schedule->id);
        self::assertSame('pending', $schedule->refresh()->state);
        self::assertSame($stream->draft_revision_id, $schedule->revision_id);
    }

    public function test_unchanged_save_does_not_create_revision_or_pause_schedule(): void
    {
        app(PostTypeRegistry::class)->for('post')->scheduledEditPolicy(ScheduledEditPolicy::Pause);
        $stream = $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'same', 'body' => 'text']);
        $this->lifecycle->approve($this->post, 'fa', $stream->draft_revision_id);
        $schedule = $this->lifecycle->schedule($this->post, 'fa', $stream->draft_revision_id, now()->addHour());
        $this->lifecycle->saveDraft($this->post, 'fa', ['body' => 'text', 'title' => 'same']);
        self::assertSame(1, PostContentRevision::count());
        self::assertSame('pending', $schedule->refresh()->state);
    }

    public function test_foreign_revision_cannot_be_published(): void
    {
        $stream = $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'private']);
        $this->lifecycle->approve($this->post, 'fa', $stream->draft_revision_id);
        $this->expectException(ValidationException::class);
        $this->lifecycle->publish($this->post, 'en', $stream->draft_revision_id);
    }

    public function test_whole_post_revision_can_own_a_scheduled_action(): void
    {
        $shared = $this->lifecycle->saveDraft($this->post, '*', ['languages' => ['fa' => ['title' => 'Scheduled']], 'shared' => []]);
        $schedule = $this->lifecycle->schedule($this->post, '*', (int) $shared->draft_revision_id, now()->addHour());
        self::assertSame($shared->draft_revision_id, $schedule->revision_id);
    }

    public function test_stale_version_rejects_changes_before_revision_creation(): void
    {
        $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'first']);
        try {
            $this->lifecycle->saveDraft($this->post, 'fa', ['title' => 'stale'], null, 0);
            self::fail('Expected conflict.');
        } catch (ConflictHttpException) {
            self::assertSame(1, PostContentRevision::count());
        }
    }

    public function test_whole_post_release_keeps_shared_fields_immutable_with_languages(): void
    {
        $stream = $this->lifecycle->saveDraft($this->post, '*', ['languages' => ['fa' => ['title' => 'live']], 'shared' => []]);
        $this->lifecycle->publish($this->post, '*', $stream->draft_revision_id);
        $shared = $this->lifecycle->saveDraft($this->post, '*', ['languages' => ['fa' => ['title' => 'live']], 'shared' => ['image' => 12]]);
        self::assertSame([], $this->lifecycle->published($this->post, 'fa')['shared']);
        $this->lifecycle->publish($this->post, '*', $shared->draft_revision_id);
        self::assertSame(['image' => 12], $this->lifecycle->published($this->post, 'fa')['shared']);
    }

    public function test_restoration_publishes_an_identical_payload_with_a_new_revision_id(): void
    {
        $stream = $this->lifecycle->saveDraft($this->post, '*', [
            'languages' => ['fa' => ['title' => 'Historical']], 'shared' => [],
        ]);
        $first = (int) $stream->draft_revision_id;
        $this->lifecycle->publish($this->post, '*', $first);
        $restored = $this->lifecycle->publishRestoration($this->post);

        self::assertNotSame($first, (int) $restored->live_revision_id);
        self::assertSame($restored->draft_revision_id, $restored->live_revision_id);
        self::assertSame('Historical', $this->lifecycle->published($this->post, 'fa')['content']['title']);
        self::assertSame(2, PostContentRevision::where('stream_id', $stream->id)->count());
    }

    public function test_disabling_workflow_mapping_keeps_live_content_and_schedule(): void
    {
        $stream = $this->lifecycle->saveDraft($this->post, '*', ['languages' => ['fa' => ['title' => 'live']], 'shared' => []]);
        $this->lifecycle->publish($this->post, '*', $stream->draft_revision_id);
        $schedule = $this->lifecycle->schedule($this->post, '*', $stream->draft_revision_id, now()->addHour());
        $definitions = new PostWorkflowDefinitions;
        $definition = $definitions->saveDraft('post', $this->graph(), false, 0);
        $preview = $definitions->preview('post', $definition->id);
        self::assertSame(1, $preview['mapping'][0]['live_count']);
        self::assertSame(1, $preview['mapping'][0]['scheduled_count']);
        $definitions->activate('post', $definition->id, $preview['fingerprint'], ['draft' => 'draft']);
        self::assertSame('live', $this->lifecycle->published($this->post, 'fa')['content']['title']);
        self::assertSame('pending', $schedule->refresh()->state);
    }

    public function test_mapping_preview_expires_when_content_changes(): void
    {
        $this->lifecycle->saveDraft($this->post, '*', ['languages' => ['fa' => ['title' => 'before']]]);
        $definitions = new PostWorkflowDefinitions;
        $definition = $definitions->saveDraft('post', $this->graph(), false, 0);
        $preview = $definitions->preview('post', $definition->id);
        $this->lifecycle->saveDraft($this->post, '*', ['languages' => ['fa' => ['title' => 'after']]]);
        $this->expectException(ConflictHttpException::class);
        $definitions->activate('post', $definition->id, $preview['fingerprint'], ['draft' => 'draft']);
    }

    public function test_activation_compiles_start_edge_and_preserves_live_revision(): void
    {
        $stream = $this->lifecycle->saveDraft($this->post, '*', ['languages' => ['fa' => ['title' => 'public']], 'shared' => []]);
        $this->lifecycle->publish($this->post, '*', $stream->draft_revision_id);
        $graph = $this->graph();
        $graph['states'][0]['is_terminal'] = true;
        $definitions = new PostWorkflowDefinitions;
        $definition = $definitions->saveDraft('post', $graph, true, 0);
        $preview = $definitions->preview('post', $definition->id);
        $definitions->activate('post', $definition->id, $preview['fingerprint'], ['draft' => 'draft']);

        self::assertSame(1, $this->database->table('flow_transitions')->whereNull('from')->count());
        self::assertSame('draft', $this->database->table('flow_states')->first()->status);
        self::assertSame('public', $this->lifecycle->published($this->post, 'fa')['content']['title']);
        self::assertNotNull($stream->refresh()->flowUse);
    }

    private function graph(): array
    {
        return ['states' => [['id' => 'draft', 'status' => 'draft', 'name' => 'Draft', 'is_start' => true, 'is_terminal' => false]], 'transitions' => []];
    }
}
