<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('post.tables.schedule', 'post_scheduled_actions'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('stream_id')->index();
            $table->unsignedBigInteger('revision_id');
            $table->string('action');
            $table->string('state')->default('pending');
            $table->string('edit_policy');
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('created_by')->nullable();
            $table->uuid('execution_key')->unique();
            $table->timestamps();
            $table->index(['state', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('post.tables.schedule', 'post_scheduled_actions'));
    }
};
