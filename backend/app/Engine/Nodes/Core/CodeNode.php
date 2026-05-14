<?php

namespace App\Engine\Nodes\Core;

use App\Contracts\NodeHandler;
use App\Engine\NodeResult;
use App\Engine\NodeInput;
use V8Js;
use V8JsException;
use V8JsMemoryLimitException;
use V8JsTimeLimitException;

/**
 * Executes JavaScript code in a sandboxed V8 environment.
 *
 * Security features:
 * - Memory limit (default 128MB)
 * - Time limit (default 30 seconds)
 * - No access to PHP functions or filesystem
 * - Isolated V8 context per execution
 */
class CodeNode implements NodeHandler
{
    private const DEFAULT_MEMORY_LIMIT = 128 * 1024 * 1024; // 128MB

    private const DEFAULT_TIME_LIMIT = 30; // 30 seconds

    public function handle(NodeInput $payload): NodeResult
    {
        $startTime = hrtime(true);

        try {
            // Check if V8Js extension is available
            if (! extension_loaded('v8js')) {
                return NodeResult::failed(
                    'V8Js extension is not installed. Please install php-v8js extension.',
                    'V8JS_NOT_AVAILABLE',
                    0
                );
            }

            $code = $payload->config['code'] ?? '';
            $memoryLimit = min($payload->config['memory_limit'] ?? self::DEFAULT_MEMORY_LIMIT, 512 * 1024 * 1024);
            $timeLimit = min($payload->config['timeout'] ?? self::DEFAULT_TIME_LIMIT, 300);

            if (empty($code)) {
                return NodeResult::failed('No code provided', 'EMPTY_CODE', 0);
            }

            $output = $this->executeCode($code, $payload->inputData, $memoryLimit, $timeLimit);

            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::completed($output, $durationMs);

        } catch (V8JsTimeLimitException $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::failed(
                'Code execution timed out',
                'TIMEOUT',
                $durationMs
            );
        } catch (V8JsMemoryLimitException $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::failed(
                'Code execution exceeded memory limit',
                'MEMORY_LIMIT',
                $durationMs
            );
        } catch (V8JsException $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::failed(
                $e->getMessage(),
                'EXECUTION_ERROR',
                $durationMs
            );
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::failed(
                $e->getMessage(),
                'UNKNOWN_ERROR',
                $durationMs
            );
        }
    }

    /**
     * Execute JavaScript code in isolated V8 context.
     *
     * @return array<string, mixed>
     */
    private function executeCode(string $code, array $inputData, int $memoryLimit, int $timeLimit): array
    {
        // Create isolated V8 context
        $v8 = new V8Js;

        // Set security limits
        $v8->setMemoryLimit($memoryLimit);
        $v8->setTimeLimit($timeLimit);

        // Inject input data
        $v8->input = $inputData;

        // Wrap user code to capture output
        $wrappedCode = <<<JS
(function() {
    // User code
    {$code}

    // Return output (user should set 'output' variable)
    return typeof output !== 'undefined' ? output : input;
})();
JS;

        $result = $v8->executeString($wrappedCode, 'user_code.js');

        // Convert V8 result to PHP array
        return $this->normalizeOutput($result);
    }

    /**
     * Normalize V8Js output to PHP array.
     *
     * @return array<string, mixed>
     */
    private function normalizeOutput(mixed $result): array
    {
        if (is_object($result)) {
            $result = json_decode(json_encode($result), true);
        }

        if (! is_array($result)) {
            return ['result' => $result];
        }

        return $result;
    }
}
