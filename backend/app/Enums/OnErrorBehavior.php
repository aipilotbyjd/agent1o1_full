<?php

namespace App\Enums;

enum OnErrorBehavior: string
{
    case Stop = 'stop';
    case Continue = 'continue';
    case ContinueErrorOutput = 'continue_error_output';

    /**
     * @param  array<string, mixed>  $node
     */
    public static function fromNode(array $node): self
    {
        $data = $node['data'] ?? [];
        $config = $node['config'] ?? [];

        $raw = $data['on_error'] ?? $config['on_error'] ?? null;

        if ($raw !== null) {
            return self::tryFrom((string) $raw) ?? self::Stop;
        }

        $legacyContinue = $data['continueOnFail'] ?? $config['continueOnFail'] ?? false;

        if ($legacyContinue) {
            return self::Continue;
        }

        return self::Stop;
    }
}
