<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('post.tables.content_revision', 'post_content_revisions'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('stream_id')->index();
            $table->json('payload');
            $table->string('checksum', 64);
            $table->string('created_by')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('post.tables.content_revision', 'post_content_revisions'));
    }
};
