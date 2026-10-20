<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('post.tables.workflow_definition', 'post_workflow_definitions'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workflow_id')->index();
            $table->unsignedInteger('version');
            $table->boolean('enabled');
            $table->string('state')->default('draft');
            $table->json('graph');
            $table->unsignedBigInteger('flow_id')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->unique(['workflow_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('post.tables.workflow_definition', 'post_workflow_definitions'));
    }
};
