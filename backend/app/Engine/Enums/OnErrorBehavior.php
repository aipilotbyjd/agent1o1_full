<?php

namespace App\Engine\Enums;

/**
 * Controls what the engine does when a node's execution fails.
 *
 * Configured per-node via the `on_error` field in node data/config.
 *
 *  stop                 – Halt the execution immediately and mark it as failed.
 *                         This is the default and matches the legacy behaviour.
 *
 *  continue             – Skip the failed node's output (treat as empty success)
 *                         and keep running all downstream nodes on the success path.
 *                         Useful for batch loops where one bad item should not kill
 *                         the rest.
 *
 *  continue_error_output – Route the failed item's error data to the node's special
 *                          "error" output handle. Only edges connected to that handle
 *                          fire; the normal success-path edges are NOT activated.
 *                          Lets users build explicit error-handling sub-flows.
 */
enum OnErrorBehavior: string
{
    case Stop = 'stop';
    case Continue = 'continue';
    case ContinueErrorOutput = 'continue_error_output';

    /**
     * Read the on_error setting from a node definition, with backward-compat
     * for the legacy `continueOnFail` boolean flag.
     *
     * Priority:
     *   1. node['data']['on_error']   (new field, string)
     *   2. node['config']['on_error'] (alternate location)
     *   3. node['data']['continueOnFail'] == true  → Continue  (legacy compat)
     *   4. node['config']['continueOnFail'] == true → Continue  (legacy compat)
     *   5. Stop  (safe default)
     *
     * @param  array<string, mixed>  $node
     */
    public static function fromNode(array $node): self
    {
        $data = $node['data'] ?? [];
        $config = $node['config'] ?? [];

        // 1 & 2 — explicit new field
        $raw = $data['on_error'] ?? $config['on_error'] ?? null;

        if ($raw !== null) {
            return self::tryFrom((string) $raw) ?? self::Stop;
        }

        // 3 & 4 — legacy boolean flag
        $legacyContinue = $data['continueOnFail'] ?? $config['continueOnFail'] ?? false;

        if ($legacyContinue) {
            return self::Continue;
        }

        return self::Stop;
    }
}
