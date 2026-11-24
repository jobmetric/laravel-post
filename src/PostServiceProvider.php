<?php

namespace JobMetric\Post;

use JobMetric\PackageCore\Exceptions\MigrationFolderNotFoundException;
use JobMetric\PackageCore\PackageCore;
use JobMetric\PackageCore\PackageCoreServiceProvider;
use JobMetric\Post\Support\PostTypeRegistry as Registry;
use JobMetric\Post\Support\ScheduledPostActionRegistry;
use JobMetric\Post\Actions\PublishPostRevision;

/**
 * Register post configuration, persistence services and type definitions.
 */
class PostServiceProvider extends PackageCoreServiceProvider
{
    /**
     * Declare package configuration, migrations and translations.
     *
     * @param PackageCore $package
     *
     * @return void
     * @throws MigrationFolderNotFoundException
     */
    public function configuration(PackageCore $package): void
    {
        $package->name('laravel-post')
            ->hasConfig()
            ->hasMigration()
            ->hasTranslation();
    }

    /**
     * Register package services and load configured post types.
     *
     * @return void
     */
    public function afterRegisterPackage(): void
    {
        $this->app->singleton(Post::class);
        $this->app->singleton(Registry::class);
        $this->app->singleton(ScheduledPostActionRegistry::class, static function (): ScheduledPostActionRegistry {
            return (new ScheduledPostActionRegistry)->register(new PublishPostRevision);
        });
        $this->app->alias(Registry::class, 'PostTypeRegistry');
        $this->app->afterResolving(\JobMetric\Flow\Support\FlowTaskRegistry::class, static function ($registry): void {
            foreach ([\JobMetric\Post\Tasks\RequireContent::class, \JobMetric\Post\Tasks\RequireExcerpt::class,
                \JobMetric\Post\Tasks\RequirePermission::class, \JobMetric\Post\Tasks\RequireApprovedRevision::class,
                \JobMetric\Post\Tasks\RecordTransition::class,
                \JobMetric\Post\Tasks\LockPostLanguage::class,
                \JobMetric\Post\Tasks\UnlockPostLanguage::class,
                \JobMetric\Post\Tasks\HideLivePost::class] as $driver) {
                if (! $registry->hasClass($driver)) {
                    $registry->register(new $driver);
                }
            }
        });
        foreach (config('post.types', []) as $type => $options) {
            app(Registry::class)->register((string) $type, is_array($options) ? $options : []);
        }
    }
    /**
     * Register committed business outcome definitions alongside legacy events.
     *
     * @return void
     */
    public function afterBootPackage(): void
    {
        if ($this->app->bound('EventRegistry')) {
            $registry = $this->app->make('EventRegistry');
            $registry->register(\JobMetric\Post\Events\Post\PostStoreCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Post\PostUpdateCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Post\PostDeleteCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Post\PostRestoreCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Post\PostForceDeleteCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\PostMedia\PostMediaSyncCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\PostTaxonomy\PostTaxonomySyncCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\PostRevision\PostRevisionStoreCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Publication\PublicationSubmitCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Publication\PublicationApproveCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Publication\PublicationRejectCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Publication\PublicationPublishCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Publication\PublicationRestoreCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Schedule\SchedulePauseCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Schedule\ScheduleStoreCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Schedule\ScheduleResumeCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Schedule\ScheduleExecuteCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Schedule\ScheduleCancelCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Publication\PublicationWithdrawCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Publication\PublicationTransitionCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Publication\PublicationUpdateCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Workflow\WorkflowStoreCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\Workflow\WorkflowActivateCommittedEvent::class);
            $registry->register(\JobMetric\Post\Events\PostStatus\PostStatusUpdateCommittedEvent::class);
        }
    }
}
