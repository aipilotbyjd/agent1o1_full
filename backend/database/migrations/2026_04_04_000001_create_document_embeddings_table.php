<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Enable pgvector extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        // Create document_embeddings table
        Schema::create('document_embeddings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('collection_name')->index();
            $table->string('document_id')->index();
            $table->integer('chunk_index')->default(0);
            $table->text('content');
            $table->vector('embedding', 1536); // OpenAI ada-002 dimension
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['workspace_id', 'collection_name']);
            $table->index(['document_id', 'chunk_index']);
        });

        // Create vector similarity index (ivfflat for cosine similarity)
        DB::statement('CREATE INDEX document_embeddings_embedding_idx ON document_embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_embeddings');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP EXTENSION IF EXISTS vector');
        }
    }
};
