<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_tags', function (Blueprint $table) {
            $table->foreignUuid('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained('tags')->cascadeOnDelete();

            $table->primary(['workflow_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_tags');
    }
};
