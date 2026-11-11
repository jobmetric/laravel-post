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
}
