<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('post.tables.approval', 'post_revision_approvals'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('revision_id')->unique();
            $table->string('approved_by')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('post.tables.approval', 'post_revision_approvals'));
    }
};
