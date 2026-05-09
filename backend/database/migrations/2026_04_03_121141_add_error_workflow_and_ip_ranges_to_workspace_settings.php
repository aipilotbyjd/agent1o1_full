<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_settings', function (Blueprint $table) {
            $table->foreignUuid('error_workflow_id')
                ->nullable()
                ->after('auto_activate_workflows')
                ->constrained('workflows')
                ->nullOnDelete();

            $table->json('allowed_ip_ranges')
                ->nullable()
                ->after('error_workflow_id')
                ->comment('CIDR ranges allowed to call workspace webhooks. Null = allow all.');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('error_workflow_id');
            $table->dropColumn('allowed_ip_ranges');
        });
    }
};
