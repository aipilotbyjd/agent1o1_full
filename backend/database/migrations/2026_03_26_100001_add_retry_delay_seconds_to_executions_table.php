<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->unsignedSmallInteger('retry_delay_seconds')
                ->default(60)
                ->after('max_attempts')
                ->comment('Base delay in seconds between retries; actual delay = retry_delay_seconds * 2^(attempt-1)');
        });
    }

    public function down(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->dropColumn('retry_delay_seconds');
        });
    }
};
