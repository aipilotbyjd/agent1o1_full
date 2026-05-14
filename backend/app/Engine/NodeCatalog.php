<?php

namespace App\Engine;

use App\Contracts\NodeHandler;
use App\Enums\NodeType;
use Illuminate\Support\Str;

/**
 * Resolves a node type string to its handler class.
 *
 * Resolution order:
 *  1. Core/Flow nodes via the NodeType enum (fast, ~13 stable cases)
 *  2. App nodes via naming convention (zero-config, unlimited scale)
 *
 * Convention for app nodes:
 *   "google_sheets.append_row" → App\Engine\Nodes\Apps\Google\GoogleSheetsNode
 *   "slack.send_message"       → App\Engine\Nodes\Apps\Slack\SlackNode
 *   "stripe.create_invoice"    → App\Engine\Nodes\Apps\Stripe\StripeNode
 *
 * The operation (after the dot) is passed via $payload->config['operation']
 * and handled inside the node class via a match() statement.
 */
class NodeCatalog
{
    /** @var array<string, class-string<NodeHandler>> */
    private static array $cache = [];

    /**
     * Aliases for mapping catalog/seeder types to engine types.
     *
     * @var array<string, string>
     */
    private static array $aliases = [
        // Triggers
        'trigger.webhook' => 'trigger',
        'trigger.schedule' => 'trigger',
        'trigger.manual' => 'trigger',

        // Flow Control
        'flow.if' => 'if',
        'flow.switch' => 'switch',
        'flow.loop' => 'loop',
        'flow.delay' => 'delay',
        'flow.merge' => 'merge',

        // Data — legacy aliases (kept for backward compat)
        'data.transform' => 'transform',
        'data.set_variable' => 'set_variable',
        'data.filter' => 'util.filter',
        'data.aggregate' => 'util.aggregate',
        'data.json_parse' => 'util.json_parse',

        // D-01 Data Transformation nodes — all route to DataNode (operation via config)
        'data.json_transform' => 'data.json_transform',
        'data.date_time' => 'data.date_time',
        'data.math' => 'data.math',
        'data.string' => 'data.string',
        'data.crypto' => 'data.crypto',
        'data.xml' => 'data.xml',
        'data.csv' => 'data.csv',
        'data.html_extract' => 'data.html_extract',
        'data.rename_keys' => 'data.rename_keys',
        'data.remove_duplicates' => 'data.remove_duplicates',
        'data.sort' => 'data.sort',
        'data.limit' => 'data.limit',
        'data.summarize' => 'data.summarize',
        'data.advanced_filter' => 'data.advanced_filter',
        'data.compare_datasets' => 'data.compare_datasets',

        // HTTP & APIs
        'http.request' => 'http_request',
        'http.graphql' => 'http_request',
        'http.webhook_response' => 'http_request',

        // Utility
        'util.code' => 'code',

        // AI — route to new multi-provider LlmNode
        'ai.llm' => 'ai.chat_completion',
        'ai.text_classifier' => 'ai.text_classifier',
        'ai.summarizer' => 'ai.summarizer',
        'ai.sentiment' => 'ai.sentiment',
        'ai.embeddings' => 'ai.embeddings',
        'ai.image_generation' => 'ai.image_generation',
        'ai.agent' => 'ai.agent',

        // Agent node — routes to the core AgentNode handler
        'agent_node' => 'agent',

        // Utility nodes resolve to their own handlers
        'util.template' => 'util.template',
        'util.logger' => 'util.logger',
        'util.error_handler' => 'util.error_handler',
        'util.filter' => 'util.filter',
        'util.aggregate' => 'util.aggregate',
        'util.json_parse' => 'util.json_parse',

        // Storage
        'storage.read_file' => 'storage.read_file',
        'storage.write_file' => 'storage.write_file',

        // Communication
        'comm.slack_message' => 'slack.send_message',
        'comm.discord_message' => 'discord.send_message',
        'comm.send_email' => 'mail.send_email',

        // D-02 Integration node shorthand aliases
        'comm.telegram_message' => 'telegram.send_message',
        'comm.twilio_sms' => 'twilio.send_sms',
        'comm.whatsapp' => 'twilio.send_whatsapp',
    ];

    /**
     * Resolve a node type string to its handler class.
     *
     * @return class-string<NodeHandler>|null
     */
    public static function resolve(string $type): ?string
    {
        if (isset(self::$cache[$type])) {
            return self::$cache[$type];
        }

        $resolvedType = self::$aliases[$type] ?? $type;

        // 1. Try core/flow enum first
        $enumCase = NodeType::tryFrom($resolvedType);
        if ($enumCase !== null) {
            return self::$cache[$type] = $enumCase->handlerClass();
        }

        // 2. Try convention-based app resolution
        $handlerClass = self::resolveAppHandler($resolvedType);
        if ($handlerClass !== null && class_exists($handlerClass)) {
            return self::$cache[$type] = $handlerClass;
        }

        return null;
    }

    /**
     * Resolve a handler instance from the container.
     */
    public static function handler(string $type): ?NodeHandler
    {
        $class = self::resolve($type);

        if ($class === null) {
            return null;
        }

        return app($class);
    }

    /**
     * Extract the operation name from a dotted type string.
     *
     * "google_sheets.append_row" → "append_row"
     * "trigger" → null
     */
    public static function operation(string $type): ?string
    {
        $resolvedType = self::$aliases[$type] ?? $type;

        if (! str_contains($resolvedType, '.')) {
            return null;
        }

        return Str::afterLast($resolvedType, '.');
    }

    /**
     * Check if a node type is an app node (has a dot separator).
     */
    public static function isAppNode(string $type): bool
    {
        $resolvedType = self::$aliases[$type] ?? $type;

        return str_contains($resolvedType, '.') && ! NodeType::tryFrom($resolvedType);
    }

    /**
     * Convention-based resolution for app node types.
     *
     * Pattern: "{app_slug}.{operation}" → App\Engine\Nodes\Apps\{AppDir}\{AppName}Node
     *
     * Examples:
     *   google_sheets  → Apps\Google\GoogleSheetsNode
     *   gmail          → Apps\Google\GmailNode
     *   slack          → Apps\Slack\SlackNode
     *   stripe         → Apps\Stripe\StripeNode
     *
     * @return class-string<NodeHandler>|null
     */
    private static function resolveAppHandler(string $type): ?string
    {
        if (! str_contains($type, '.')) {
            return null;
        }

        $appSlug = Str::beforeLast($type, '.');

        // Check the explicit directory mapping first (for grouped apps like Google)
        $mapping = self::appDirectoryMap();

        if (isset($mapping[$appSlug])) {
            [$directory, $className] = $mapping[$appSlug];

            return "App\\Engine\\Nodes\\Apps\\{$directory}\\{$className}";
        }

        // Fallback: auto-generate from slug
        // "slack" → Apps\Slack\SlackNode
        $appName = Str::studly($appSlug);
        $directory = $appName;

        return "App\\Engine\\Nodes\\Apps\\{$directory}\\{$appName}Node";
    }

    /**
     * Explicit mapping for apps that share a directory or have non-standard names.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private static function appDirectoryMap(): array
    {
        return [
            // Google apps share the Google/ directory
            'google_sheets' => ['Google', 'GoogleSheetsNode'],
            'gmail' => ['Google', 'GmailNode'],
            'google_drive' => ['Google', 'GoogleDriveNode'],
            'google_calendar' => ['Google', 'GoogleCalendarNode'],

            // AI — new multi-provider node (Laravel AI SDK)
            'ai' => ['Ai', 'LlmNode'],

            // OpenAI — kept for backward compatibility
            'openai' => ['OpenAi', 'OpenAiNode'],

            // Utility nodes
            'util' => ['Util', 'UtilNode'],

            // Storage nodes
            'storage' => ['Storage', 'StorageNode'],

            // Stripe
            'stripe' => ['Stripe', 'StripeNode'],

            // Airtable
            'airtable' => ['Airtable', 'AirtableNode'],

            // Discord
            'discord' => ['Discord', 'DiscordNode'],

            // Mail
            'mail' => ['Mail', 'MailNode'],

            // GitHub
            'github' => ['GitHub', 'GitHubNode'],

            // ─── D-01: Data Transformation ───────────────────────────────────────
            'data' => ['Data', 'DataNode'],

            // ─── D-02: Integration Nodes ─────────────────────────────────────────

            // Communication
            'telegram' => ['Telegram', 'TelegramNode'],
            'twilio' => ['Twilio', 'TwilioNode'],

            // Project Management
            'trello' => ['Trello', 'TrelloNode'],
            'gitlab' => ['Gitlab', 'GitlabNode'],
            'jira' => ['Jira', 'JiraNode'],
            'linear' => ['Linear', 'LinearNode'],

            // CRM / Marketing
            'hubspot' => ['Hubspot', 'HubspotNode'],
            'salesforce' => ['Salesforce', 'SalesforceNode'],
            'mailchimp' => ['Mailchimp', 'MailchimpNode'],
            'sendgrid' => ['Sendgrid', 'SendgridNode'],

            // Social
            'twitch' => ['Twitch', 'TwitchNode'],
            'twitter' => ['Twitter', 'TwitterNode'],

            // Databases
            'mysql' => ['Mysql', 'MysqlNode'],
            'postgresql' => ['Postgresql', 'PostgresqlNode'],
            'mongodb' => ['Mongodb', 'MongodbNode'],
            'redis' => ['Redis', 'RedisNode'],

            // Storage
            'ftp' => ['Ftp', 'FtpNode'],
            'aws_s3' => ['AwsS3', 'AwsS3Node'],
            'dropbox' => ['Dropbox', 'DropboxNode'],
        ];
    }

    /**
     * Return the latest schema version number for a given node type.
     *
     * Falls back to 1 if the node type is not in the nodes table (e.g. core/flow
     * nodes that are not catalogue-registered). The result is cached in-process
     * for the lifetime of the request — no repeated DB round-trips per execution.
     *
     * @return int  The current version from nodes.version, or 1 if unknown.
     */
    public static function latestVersion(string $type): int
    {
        static $versionCache = [];

        if (isset($versionCache[$type])) {
            return $versionCache[$type];
        }

        $resolvedType = self::$aliases[$type] ?? $type;

        $node = \App\Models\Node::query()
            ->select('version')
            ->where('type', $resolvedType)
            ->first();

        return $versionCache[$type] = $node ? (int) $node->version : 1;
    }

    /**
     * Clear the resolution cache (for testing).
     */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
