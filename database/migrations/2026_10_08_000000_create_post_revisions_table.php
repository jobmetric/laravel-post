<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('post.tables.revision', 'post_revisions'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained(config('post.tables.post', 'posts'))->cascadeOnDelete();
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['post_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists(config('post.tables.revision', 'post_revisions')); }
};
