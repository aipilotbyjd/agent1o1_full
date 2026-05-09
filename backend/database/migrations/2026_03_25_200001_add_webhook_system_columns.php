<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds columns required for the async webhook registration architecture:
 *
 * workflows.webhook_status        — tracks whether external webhook registration
 *                                   has completed, is pending, or failed.
 *                                   Null means the workflow has no external webhooks.
 *
 * workflows.webhook_status_message — stores the last error message when
 *                                    registration fails, shown to the user.
 *
 * webhooks.registered_url         — the exact callback URL sent to the external
 *                                   provider (GitHub, Stripe, etc.) at registration
 *                                   time. Used to detect URL changes and trigger
 *                                   automatic re-registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            // Possible values: pending | active | failed | deregistering | null
            $table->string('webhook_status', 20)->nullable()->after('is_active');
            $table->text('webhook_status_message')->nullable()->after('webhook_status');
        });

        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('registered_url')->nullable()->after('external_webhook_secret');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn(['webhook_status', 'webhook_status_message']);
        });

        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropColumn('registered_url');
        });
    }
};
