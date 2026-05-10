<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_concurrent_executions')
                ->default(0)
                ->after('error_workflow_id')
                ->comment('0 = unlimited; >0 caps simultaneous active executions per workflow');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('max_concurrent_executions');
        });
    }
};
