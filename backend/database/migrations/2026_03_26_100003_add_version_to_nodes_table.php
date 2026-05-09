<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            // Monotonically increasing integer. Bumped whenever the node's
            // config_schema, input_schema, or output_schema changes in a
            // breaking way. WorkflowVersionService stamps this value into each
            // node object in the workflow version's nodes JSON at save time.
            // The WorkflowVersion show endpoint then exposes `outdated_nodes`
            // by comparing stored _node_version against the current value here.
            $table->unsignedInteger('version')->default(1)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
