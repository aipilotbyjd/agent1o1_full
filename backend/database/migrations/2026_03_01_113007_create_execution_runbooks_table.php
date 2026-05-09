<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_runbooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('execution_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->string('title');
            $table->text('summary');
            $table->json('steps');
            $table->json('tags')->nullable();
            $table->enum('status', ['open', 'acknowledged', 'resolved']);
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_runbooks');
    }
};
