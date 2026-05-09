<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('name', 100);
            $table->string('color', 20)->default('#6366f1');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'parent_id', 'name']);
            $table->index('parent_id');
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('folders')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
