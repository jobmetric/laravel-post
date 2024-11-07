<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(config('post.tables.post'), function (Blueprint $table) {
            $table->id();

            $table->string('type')->index();
            /**
             * The type field is used to distinguish different types of posts.
             * For example, post or page.
             */

            $table->boolean('comment_status')->default(true)->index();
            /**
             * The comment_status field is used to determine whether the post can be commented on.
             */

            $table->string('password')->nullable();
            /**
             * The password field is used to set a password for the post.
             */

            $table->string('status')->index();
            /**
             * The post of the status field is used to distinguish the status of the post.
             *
             * @see \JobMetric\Post\Enums\PostStatusEnum
             */

            $table->dateTime('published_at')->nullable()->index();
            /**
             * The published_at field is used to determine the date and time the post was published or will be published.
             */

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(config('post.tables.post'));
    }
};
