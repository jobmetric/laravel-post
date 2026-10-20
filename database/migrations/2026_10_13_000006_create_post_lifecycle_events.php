<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('post.tables.lifecycle_event', 'post_lifecycle_events'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('post_id')->nullable()->index();
            $table->unsignedBigInteger('stream_id')->nullable()->index();
            $table->unsignedBigInteger('revision_id')->nullable();
            $table->string('event');
            $table->string('actor_id')->nullable();
            $table->json('context');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('post.tables.lifecycle_event', 'post_lifecycle_events'));
    }
};
