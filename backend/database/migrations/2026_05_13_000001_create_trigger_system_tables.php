<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ════════════════════════════════════════════════════════
        // TABLE 1: trigger_categories
        // ════════════════════════════════════════════════════════
        Schema::create('trigger_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();

            $table->enum('category_type', ['manual', 'schedule', 'webhook', 'polling', 'app_specific']);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category_type');
        });

        // ════════════════════════════════════════════════════════
        // TABLE 2: trigger_types
        // ════════════════════════════════════════════════════════
        Schema::create('trigger_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('trigger_categories')->cascadeOnDelete();

            $table->string('slug', 100)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();

            $table->enum('execution_mode', ['manual', 'webhook', 'polling']);
            $table->enum('zapier_mode', ['instant', 'polling'])->nullable();

            $table->boolean('requires_credential')->default(false);
            $table->boolean('requires_config_fields')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('category_id');
            $table->index('execution_mode');
        });

        // ════════════════════════════════════════════════════════
        // TABLE 3: trigger_type_fields
        // ════════════════════════════════════════════════════════
        Schema::create('trigger_type_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trigger_type_id')->constrained('trigger_types')->cascadeOnDelete();

            $table->string('field_name', 100);
            $table->string('field_label', 100);
            $table->enum('field_type', ['text', 'number', 'select', 'multiselect', 'date', 'time', 'cron', 'textarea']);

            $table->boolean('is_required')->default(false);
            $table->boolean('is_secret')->default(false);

            $table->string('placeholder', 255)->nullable();
            $table->text('help_text')->nullable();
            $table->string('validation_regex', 500)->nullable();

            $table->json('options')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreignId('trigger_type_id')->constrained('trigger_types')->cascadeOnDelete();
            $table->unique(['trigger_type_id', 'field_name']);
            $table->index('trigger_type_id');
        });

        // ════════════════════════════════════════════════════════
        // TABLE 4: triggers
        // ════════════════════════════════════════════════════════
        Schema::create('triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->unique()->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();

            $table->foreignId('trigger_type_id')->constrained('trigger_types');
            $table->foreignId('trigger_category_id')->constrained('trigger_categories');
            $table->foreignId('credential_id')->nullable()->constrained('credentials')->nullOnDelete();

            $table->string('name', 255)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_published')->default(false);

            // ─────────────────────────────────────────────
            // Webhook fields
            // ─────────────────────────────────────────────
            $table->string('webhook_uuid', 36)->unique()->nullable();
            $table->string('webhook_provider', 50)->nullable();
            $table->string('webhook_external_id', 255)->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('webhook_registered_url', 255)->nullable();
            $table->enum('webhook_status', ['pending', 'active', 'failed'])->nullable();
            $table->text('webhook_status_message')->nullable();

            // ─────────────────────────────────────────────
            // Polling fields
            // ─────────────────────────────────────────────
            $table->integer('polling_interval_seconds')->nullable();
            $table->timestamp('polling_last_check_at')->nullable();
            $table->json('polling_last_seen_ids')->nullable();
            $table->string('polling_endpoint_url', 255)->nullable();

            // ─────────────────────────────────────────────
            // Schedule fields
            // ─────────────────────────────────────────────
            $table->string('schedule_expression', 255)->nullable();
            $table->timestamp('schedule_next_run_at')->nullable();
            $table->string('schedule_timezone', 50)->nullable();
            $table->timestamp('schedule_last_run_at')->nullable();

            // ─────────────────────────────────────────────
            // Error tracking
            // ─────────────────────────────────────────────
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->integer('consecutive_errors')->default(0);

            // ─────────────────────────────────────────────
            // Stats
            // ─────────────────────────────────────────────
            $table->integer('trigger_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();

            $table->timestamps();

            $table->index('workflow_id');
            $table->index('workspace_id');
            $table->index('trigger_type_id');
            $table->index(['is_active', 'is_published']);
            $table->index('schedule_next_run_at');
        });

        // ════════════════════════════════════════════════════════
        // TABLE 5: trigger_field_values
        // ════════════════════════════════════════════════════════
        Schema::create('trigger_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trigger_id')->constrained('triggers')->cascadeOnDelete();
            $table->foreignId('trigger_type_field_id')->constrained('trigger_type_fields')->cascadeOnDelete();

            $table->longText('value')->nullable();

            $table->timestamps();

            $table->unique(['trigger_id', 'trigger_type_field_id']);
            $table->index('trigger_id');
        });

        // ════════════════════════════════════════════════════════
        // TABLE 6: trigger_executions
        // ════════════════════════════════════════════════════════
        Schema::create('trigger_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trigger_id')->constrained('triggers')->cascadeOnDelete();
            $table->string('workflow_execution_id', 36)->nullable();

            $table->enum('source', ['manual', 'webhook', 'polling', 'schedule']);
            $table->timestamp('triggered_at');

            $table->json('trigger_payload')->nullable();

            $table->enum('status', ['success', 'failed', 'skipped'])->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('trigger_id');
            $table->index('created_at');
            $table->index('workflow_execution_id');
            $table->foreign('workflow_execution_id')->references('id')->on('executions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trigger_executions');
        Schema::dropIfExists('trigger_field_values');
        Schema::dropIfExists('triggers');
        Schema::dropIfExists('trigger_type_fields');
        Schema::dropIfExists('trigger_types');
        Schema::dropIfExists('trigger_categories');
    }
};
