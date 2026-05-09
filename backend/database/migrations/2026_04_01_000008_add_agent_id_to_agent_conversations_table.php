<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->uuid('agent_id')->nullable()->after('id');
            $table->uuid('workspace_id')->nullable()->after('agent_id');

            $table->index(['agent_id', 'updated_at']);
            $table->index('workspace_id');

            $table->foreign('agent_id')->references('id')->on('agents')->nullOnDelete();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropForeign(['workspace_id']);
            $table->dropIndex(['agent_id', 'updated_at']);
            $table->dropIndex(['workspace_id']);
            $table->dropColumn(['agent_id', 'workspace_id']);
        });
    }
};
