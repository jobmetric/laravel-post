<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(config('post.tables.post_relation'), function (Blueprint $table) {
            $table->foreignId('post_id')->index()
                ->references('id')->on(config('post.tables.post'))->cascadeOnDelete()->cascadeOnUpdate();

            $table->morphs('postable');
            /**
             * relatable to:
             *
             * Product
             * Service
             * Currency
             * ...
             */

            $table->string('collection')->nullable();
            /**
             * for another collection file
             *
             * null value for base collection
             */

            $table->dateTime('created_at')->index()->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->unique([
                'post_id',
                'postable_type',
                'postable_id',
                'collection'
            ], 'POST_RELATION_UNIQUE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(config('post.tables.post_relation'));
    }
};
