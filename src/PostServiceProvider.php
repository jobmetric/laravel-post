<?php

namespace JobMetric\Post;

use Illuminate\Support\Facades\Route;
use JobMetric\PackageCore\Exceptions\MigrationFolderNotFoundException;
use JobMetric\PackageCore\PackageCore;
use JobMetric\PackageCore\PackageCoreServiceProvider;
use JobMetric\Post\Events\PostTypeEvent;

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
            ->hasTranslation()
            ->registerClass("Post", Post::class);
    }

    /**
     * After register package
     *
     * @return void
     */
    public function afterRegisterPackage(): void
    {
        $this->app->singleton('postType', function () {
            $event = new PostTypeEvent;
            event($event);

            return $event->postType;
        });

        // Register model binding
        Route::model('jm_post', \JobMetric\Post\Models\Post::class);
        Route::model('jm_post_relation', \JobMetric\Post\Models\PostRelation::class);
    }
}
