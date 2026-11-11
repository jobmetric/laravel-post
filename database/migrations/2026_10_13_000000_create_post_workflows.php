<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Create the type-scoped workflow coordinator. */
return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('post.tables.workflow', 'post_workflows'), function (Blueprint $table): void {
            $table->id();
            $table->string('type')->unique();
            $table->unsignedBigInteger('active_definition_id')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('post.tables.workflow', 'post_workflows'));
    }
};
