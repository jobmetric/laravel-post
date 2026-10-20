<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('post.tables.locale_lock', 'post_locale_locks'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('post_id')->index();
            $table->string('locale', 32);
            $table->boolean('locked')->default(false);
            $table->string('changed_by')->nullable();
            $table->timestamps();
            $table->unique(['post_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('post.tables.locale_lock', 'post_locale_locks'));
    }
};
