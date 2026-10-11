<?php

namespace JobMetric\Post;

use JobMetric\PackageCore\Exceptions\MigrationFolderNotFoundException;
use JobMetric\PackageCore\PackageCore;
use JobMetric\PackageCore\PackageCoreServiceProvider;
use JobMetric\Post\Support\PostTypeRegistry as Registry;

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
        $this->app->alias(Registry::class, 'PostTypeRegistry');
        foreach (config('post.types', []) as $type => $options) {
            app(Registry::class)->register((string) $type, is_array($options) ? $options : []);
        }
    }
}
