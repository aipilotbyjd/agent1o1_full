<?php

namespace App\Agents\Tools;

use App\Models\AgentSkillScript;
use Laravel\Ai\Contracts\Tool;

/**
 * Wraps an AgentSkillScript as a callable Laravel AI tool.
 *
 * When the agent decides to use this tool, the script code is executed
 * in a sandboxed environment and the output is returned as the tool result.
 *
 * Supported languages: php, javascript (via node CLI).
 */
class SkillScriptTool implements Tool
{
    public function __construct(
        private readonly AgentSkillScript $script,
    ) {}

    public function name(): string
    {
        return 'skill_script_' . str_replace([' ', '-'], '_', strtolower($this->script->name));
    }

    public function description(): string
    {
        return $this->script->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'input' => [
                    'type' => 'string',
                    'description' => 'Input data to pass to the script as a JSON string.',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * Execute the script and return its output.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments): string
    {
        $input = $arguments['input'] ?? '';

        try {
            return match ($this->script->language) {
                'php' => $this->runPhp($input),
                'javascript' => $this->runJavaScript($input),
                default => json_encode(['error' => "Unsupported language: {$this->script->language}"]),
            };
        } catch (\Throwable $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    private function runPhp(string $input): string
    {
        $code = $this->script->code;
        $tmpFile = tempnam(sys_get_temp_dir(), 'skill_') . '.php';

        $wrapper = "<?php\n\$input = json_decode(<<<'INPUT'\n{$input}\nINPUT, true);\n\n{$code}";
        file_put_contents($tmpFile, $wrapper);

        $output = shell_exec('php ' . escapeshellarg($tmpFile) . ' 2>&1');
        @unlink($tmpFile);

        return $output ?? '';
    }

    private function runJavaScript(string $input): string
    {
        $code = $this->script->code;
        $tmpFile = tempnam(sys_get_temp_dir(), 'skill_') . '.js';

        $wrapper = "const input = JSON.parse(`{$input}`);\n\n{$code}";
        file_put_contents($tmpFile, $wrapper);

        $output = shell_exec('node ' . escapeshellarg($tmpFile) . ' 2>&1');
        @unlink($tmpFile);

        return $output ?? '';
    }
}
