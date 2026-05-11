<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your Horizon instance. This value will be
    | used when the dashboard is shown and if Horizon is reporting data
    | to external services such as New Relic or Datadog.
    |
    */

    'name' => env('HORIZON_NAME', env('APP_NAME', 'Workflow Engine')),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as your
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the path of its internal API that isn't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations of
    | Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Thresholds (seconds)
    |--------------------------------------------------------------------------
    | Fire LongWaitDetected event when a queue backs up past this threshold.
    | Critical & high queues are tighter — they must stay low-latency.
    |
    | Queue priority order (highest → lowest):
    |   critical          → Slack/Discord handshakes; must be < 1s
    |   workflows-high    → Webhook processing (GitHub, Stripe events)
    |   workflows-default → Workflow executions, registration
    |   long-running      → AI diagnosis, complex multi-step executions
    |   maintenance       → Health checks, polling, cron dispatch
    |   notifications     → User notifications
    |   workflows-low     → Auto-retry executions (exponential backoff, delayed)
    |   default           → Everything else
    */

    'waits' => [
        'redis:critical'          => 10,
        'redis:workflows-high'    => 30,
        'redis:workflows-default' => 60,
        'redis:long-running'      => 300,
        'redis:maintenance'       => 120,
        'redis:notifications'     => 60,
        'redis:workflows-low'     => 600,
        'redis:default'           => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent'         => 60,
        'pending'        => 60,
        'completed'      => 60,
        'recent_failed'  => 10080,
        'failed'         => 10080,
        'monitored'      => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to represent a
    | temporary historical look at the metrics within Horizon. Each snapshot is
    | taken every five minutes, so by default, two days of snapshots are kept.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the timeout value
    | expires. This should be enabled in most production environments.
    |
    */

    'fast_termination' => env('APP_ENV') === 'production',

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Five supervisor tiers, each draining a specific set of queues.
    | Workers always drain queues left-to-right (highest priority first).
    |
    | Supervisor breakdown:
    |
    |   supervisor-critical
    |     Drains:  critical → workflows-high
    |     Purpose: Webhook handshakes (Slack challenge, Discord PING) that
    |              providers reject if not answered within ~3 seconds.
    |              Always has capacity — never starved by other work.
    |     Workers: 3 base → up to 10 in production
    |
    |   supervisor-workflows
    |     Drains:  workflows-high → workflows-default
    |     Purpose: GitHub/Stripe webhook dispatch + workflow execution.
    |              The hot path. Auto-scales aggressively under load.
    |     Workers: 5 base → up to 20 in production
    |
    |   supervisor-long-running
    |     Drains:  long-running → workflows-default (overflow)
    |     Purpose: AI diagnosis, multi-minute DAG executions.
    |              High timeout (600s). Small pool so long jobs don't starve
    |              other supervisors.
    |     Workers: 2 base → up to 5 in production
    |
    |   supervisor-maintenance
    |     Drains:  maintenance → default
    |     Purpose: Webhook health checks, per-trigger polling jobs,
    |              cron dispatch. Low priority. 1-2 workers is fine.
    |     Workers: 1 base → up to 3 in production
    |
    |   supervisor-notifications
    |     Drains:  notifications → default
    |     Purpose: User-facing notifications. Isolated so a notification
    |              backlog never blocks workflow processing.
    |     Workers: 2 base → up to 5 in production
    |
    |   supervisor-retries
    |     Drains:  workflows-low → default
    |     Purpose: Auto-retry executions dispatched with exponential backoff
    |              delays. Kept separate so delayed retry jobs never compete
    |              with live workflow processing. Jobs arrive here already
    |              past their delay window so processing is nearly instant.
    |     Workers: 1 base → up to 3 in production
    */

    'defaults' => [

        'supervisor-critical' => [
            'connection'          => 'redis',
            'queue'               => ['critical', 'workflows-high'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 2,
            'maxProcesses'        => 3,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 2,
            'timeout'             => 15,
            'nice'                => -5,
        ],

        'supervisor-workflows' => [
            'connection'          => 'redis',
            'queue'               => ['workflows-high', 'workflows-default'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 2,
            'maxProcesses'        => 5,
            'balanceMaxShift'     => 3,
            'balanceCooldown'     => 3,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 256,
            'tries'               => 3,
            'timeout'             => 120,
            'nice'                => 0,
        ],

        'supervisor-long-running' => [
            'connection'          => 'redis',
            'queue'               => ['long-running', 'workflows-default'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 1,
            'maxProcesses'        => 2,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 512,
            'tries'               => 1,
            'timeout'             => 600,
            'nice'                => 5,
        ],

        'supervisor-maintenance' => [
            'connection'          => 'redis',
            'queue'               => ['maintenance', 'default'],
            'balance'             => 'simple',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 1,
            'maxProcesses'        => 1,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 3,
            'timeout'             => 90,
            'nice'                => 10,
        ],

        'supervisor-notifications' => [
            'connection'          => 'redis',
            'queue'               => ['notifications', 'default'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 1,
            'maxProcesses'        => 2,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 3,
            'timeout'             => 30,
            'nice'                => 0,
        ],

        'supervisor-retries' => [
            'connection'          => 'redis',
            'queue'               => ['workflows-low', 'default'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 1,
            'maxProcesses'        => 2,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 256,
            'tries'               => 1,
            'timeout'             => 120,
            'nice'                => 10,
        ],

    ],

    'environments' => [

        'production' => [
            'supervisor-critical' => [
                'minProcesses'    => 3,
                'maxProcesses'    => 10,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 2,
            ],
            'supervisor-workflows' => [
                'minProcesses'    => 8,
                'maxProcesses'    => 20,
                'balanceMaxShift' => 5,
                'balanceCooldown' => 2,
            ],
            'supervisor-long-running' => [
                'minProcesses'    => 2,
                'maxProcesses'    => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 10,
            ],
            'supervisor-maintenance' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 5,
            ],
            'supervisor-notifications' => [
                'minProcesses'    => 2,
                'maxProcesses'    => 5,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],
            'supervisor-retries' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 5,
            ],
        ],

        'staging' => [
            'supervisor-critical' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-workflows' => [
                'minProcesses'    => 2,
                'maxProcesses'    => 6,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],
            'supervisor-long-running' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 10,
            ],
            'supervisor-maintenance' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 1,
            ],
            'supervisor-notifications' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-retries' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 5,
            ],
        ],

        'local' => [
            'supervisor-critical' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-workflows' => [
                'minProcesses' => 1,
                'maxProcesses' => 3,
            ],
            'supervisor-long-running' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-maintenance' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-notifications' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
            'supervisor-retries' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Horizon Watch
    |--------------------------------------------------------------------------
    |
    | This option allows you to specify the paths that Horizon should watch
    | for changes to automatically trigger a new worker process restart.
    | This is useful during local development to keep workers current.
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],

];
