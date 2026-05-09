<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Alert Recipients
    |--------------------------------------------------------------------------
    |
    | Email addresses that receive admin-level platform alerts.
    | Set ADMIN_ALERT_EMAIL in .env. Multiple addresses can be separated by
    | commas: "alice@example.com,bob@example.com"
    |
    */

    'admin_emails' => array_filter(
        array_map('trim', explode(',', env('ADMIN_ALERT_EMAIL', '')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Admin Slack Webhook
    |--------------------------------------------------------------------------
    |
    | Slack incoming webhook URL for platform-level admin alerts.
    | Leave empty to disable Slack admin alerts.
    |
    */

    'admin_slack_webhook' => env('ADMIN_SLACK_WEBHOOK_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Admin Discord Webhook
    |--------------------------------------------------------------------------
    */

    'admin_discord_webhook' => env('ADMIN_DISCORD_WEBHOOK_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Admin Generic Webhook
    |--------------------------------------------------------------------------
    */

    'admin_webhook_url' => env('ADMIN_WEBHOOK_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Quota Warning Threshold
    |--------------------------------------------------------------------------
    |
    | Percentage of credit usage at which to fire the QuotaWarning notification.
    | Default: 80 (fire when 80% of credits are consumed).
    |
    */

    'quota_warning_threshold' => (int) env('QUOTA_WARNING_THRESHOLD', 80),

    /*
    |--------------------------------------------------------------------------
    | Admin Alert Thresholds
    |--------------------------------------------------------------------------
    */

    'thresholds' => [
        // Fire AdminHighErrorRate when error rate exceeds this % in a sliding window
        'error_rate_percent'      => (int) env('ALERT_ERROR_RATE_PERCENT', 20),

        // Fire AdminExecutionOverflow when concurrent executions exceed this number
        'max_concurrent_executions' => (int) env('ALERT_MAX_CONCURRENT_EXECUTIONS', 100),

        // Fire AdminExecutionQueueBacklog when queue depth exceeds this count
        'queue_backlog_limit'     => (int) env('ALERT_QUEUE_BACKLOG_LIMIT', 500),

        // Fire AdminDiskUsageHigh when disk usage exceeds this percentage
        'disk_usage_percent'      => (int) env('ALERT_DISK_USAGE_PERCENT', 85),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Queue
    |--------------------------------------------------------------------------
    */

    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Webhook Timeout (seconds)
    |--------------------------------------------------------------------------
    */

    'webhook_timeout' => (int) env('NOTIFICATION_WEBHOOK_TIMEOUT', 10),

];
