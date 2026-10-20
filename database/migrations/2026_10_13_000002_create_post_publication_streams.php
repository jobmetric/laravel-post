<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('post.tables.stream', 'post_publication_streams'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('locale', 32);
            $table->string('status')->default('draft');
            $table->string('content_status', 80)->default('draft');
            $table->unsignedBigInteger('definition_id')->nullable();
            $table->unsignedBigInteger('draft_revision_id')->nullable();
            $table->unsignedBigInteger('submitted_revision_id')->nullable();
            $table->unsignedBigInteger('approved_revision_id')->nullable();
            $table->unsignedBigInteger('live_revision_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->unique(['post_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('post.tables.stream', 'post_publication_streams'));
    }
};
