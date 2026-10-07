<?php

namespace JobMetric\Post;

use JobMetric\PackageCore\Exceptions\MigrationFolderNotFoundException;
use JobMetric\PackageCore\PackageCore;
use JobMetric\PackageCore\PackageCoreServiceProvider;
use JobMetric\Post\Support\PostTypeRegistry as Registry;

class PostServiceProvider extends PackageCoreServiceProvider
{
    /**
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

    public function afterRegisterPackage(): void
    {
        $this->app->singleton(Registry::class);
        $this->app->alias(Registry::class, 'PostTypeRegistry');
        foreach (config('post.types', []) as $type => $options) {
            app(Registry::class)->register((string) $type, is_array($options) ? $options : []);
        }
    }
}
