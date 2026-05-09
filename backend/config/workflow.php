<?php

return [
    'async_max_concurrency' => env('WORKFLOW_ASYNC_CONCURRENCY', 4),
    'batch_flush_threshold' => env('WORKFLOW_BATCH_FLUSH_THRESHOLD', 100),
    'batch_flush_interval' => env('WORKFLOW_BATCH_FLUSH_INTERVAL', 1.0),

    /*
    |--------------------------------------------------------------------------
    | SSE Node Event Threshold
    |--------------------------------------------------------------------------
    |
    | Maximum number of per-node SSE events (node_started + node_completed)
    | to publish during a single execution. Once this limit is reached, node-
    | level events are suppressed to reduce Redis round-trips on large
    | workflows. Execution-level events (started, completed, failed, etc.)
    | are always published regardless of this threshold.
    |
    | Set to 0 to disable all node-level SSE events.
    | Set to -1 to publish all events unconditionally (legacy behaviour).
    |
    */
    'sse_node_event_threshold' => env('WORKFLOW_SSE_NODE_THRESHOLD', 200),
];
