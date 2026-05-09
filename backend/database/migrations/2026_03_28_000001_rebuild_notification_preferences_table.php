<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds the notification_preferences table with the proper schema.
 * The original migration created only an empty table (id + timestamps).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->foreignUuid('user_id')
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            // The notification type key (matches NotificationType enum values)
            $table->string('type')->after('user_id');

            // JSON array of enabled channel driver names: ["database","mail","slack"]
            $table->json('channels')->after('type');

            // Master switch — false means no notifications of this type at all
            $table->boolean('enabled')->default(true)->after('channels');

            $table->unique(['user_id', 'type'], 'notif_prefs_user_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'type', 'channels', 'enabled']);
        });
    }
};
