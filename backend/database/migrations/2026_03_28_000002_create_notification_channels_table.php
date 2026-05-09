<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores user-configured delivery endpoints for external channels.
 *
 * Each row represents one configured destination:
 *   - Slack incoming webhook URL
 *   - Discord channel webhook URL
 *   - Generic HTTP webhook URL
 *   - SMS phone number (via Twilio)
 *
 * Users may have multiple rows per channel type (e.g., two Slack channels).
 * The `config` column is encrypted JSON and holds the channel-specific data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Channel type: slack | discord | webhook | sms
            $table->string('channel');

            // Human-friendly label set by the user, e.g. "Team Alerts #ops"
            $table->string('label');

            // Encrypted JSON payload. Shape varies by channel:
            //   slack/discord: { "url": "https://hooks.slack.com/..." }
            //   webhook:       { "url": "https://...", "secret": "..." }
            //   sms:           { "phone": "+14155552671" }
            $table->text('config');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channels');
    }
};
