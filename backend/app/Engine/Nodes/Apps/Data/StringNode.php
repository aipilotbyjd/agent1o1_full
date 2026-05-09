<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use Illuminate\Support\Str;

/**
 * String Operations Node
 * 
 * Comprehensive string manipulation operations.
 */
class StringNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'STRING_ERROR';
    }

    protected function operations(): array
    {
        return [
            'concat' => $this->concat(...),
            'split' => $this->split(...),
            'replace' => $this->replace(...),
            'regex' => $this->regex(...),
            'case' => $this->changeCase(...),
            'trim' => $this->trim(...),
            'substring' => $this->substring(...),
            'template' => $this->template(...),
            'length' => $this->length(...),
        ];
    }

    /**
     * Concatenate strings
     */
    private function concat(NodePayload $payload): array
    {
        $strings = $payload->config['strings'] ?? [];
        $separator = $payload->config['separator'] ?? '';

        if (! is_array($strings)) {
            $strings = [$strings];
        }

        $result = implode($separator, $strings);

        return [
            'result' => $result,
            'length' => strlen($result),
        ];
    }

    /**
     * Split string
     */
    private function split(NodePayload $payload): array
    {
        $string = $payload->config['string'] ?? '';
        $delimiter = $payload->config['delimiter'] ?? ',';
        $limit = $payload->config['limit'] ?? null;
        $trimItems = (bool) ($payload->config['trim_items'] ?? true);

        if ($limit !== null) {
            $parts = explode($delimiter, $string, $limit);
        } else {
            $parts = explode($delimiter, $string);
        }

        if ($trimItems) {
            $parts = array_map('trim', $parts);
        }

        return [
            'items' => $parts,
            'count' => count($parts),
        ];
    }

    /**
     * Replace text
     */
    private function replace(NodePayload $payload): array
    {
        $string = $payload->config['string'] ?? '';
        $search = $payload->config['search'] ?? '';
        $replace = $payload->config['replace'] ?? '';
        $caseSensitive = (bool) ($payload->config['case_sensitive'] ?? true);

        if ($caseSensitive) {
            $result = str_replace($search, $replace, $string);
        } else {
            $result = str_ireplace($search, $replace, $string);
        }

        return [
            'result' => $result,
            'replacements' => substr_count(strtolower($string), strtolower($search)),
        ];
    }

    /**
     * Regex operations
     */
    private function regex(NodePayload $payload): array
    {
        $string = $payload->config['string'] ?? '';
        $pattern = $payload->config['pattern'] ?? '';
        $operation = $payload->config['operation'] ?? 'match'; // match | replace | extract

        if (empty($pattern)) {
            throw new \InvalidArgumentException('Regex pattern is required');
        }

        // Ensure pattern has delimiters
        if (! preg_match('/^[\/\#\@\~]/', $pattern)) {
            $pattern = '/'.$pattern.'/';
        }

        switch ($operation) {
            case 'match':
                $matches = preg_match($pattern, $string);

                return [
                    'matches' => (bool) $matches,
                    'pattern' => $pattern,
                ];

            case 'replace':
                $replacement = $payload->config['replacement'] ?? '';
                $result = preg_replace($pattern, $replacement, $string);

                return [
                    'result' => $result,
                    'pattern' => $pattern,
                ];

            case 'extract':
                preg_match_all($pattern, $string, $matches);

                return [
                    'matches' => $matches[0] ?? [],
                    'count' => count($matches[0] ?? []),
                    'pattern' => $pattern,
                ];

            default:
                throw new \InvalidArgumentException("Unknown operation: {$operation}");
        }
    }

    /**
     * Change case
     */
    private function changeCase(NodePayload $payload): array
    {
        $string = $payload->config['string'] ?? '';
        $case = $payload->config['case'] ?? 'lower'; // lower | upper | title | camel | snake | kebab

        $result = match ($case) {
            'lower' => strtolower($string),
            'upper' => strtoupper($string),
            'title' => ucwords(strtolower($string)),
            'camel' => Str::camel($string),
            'snake' => Str::snake($string),
            'kebab' => Str::kebab($string),
            'studly' => Str::studly($string),
            default => $string,
        };

        return [
            'result' => $result,
            'case' => $case,
        ];
    }

    /**
     * Trim whitespace
     */
    private function trim(NodePayload $payload): array
    {
        $string = $payload->config['string'] ?? '';
        $type = $payload->config['type'] ?? 'both'; // both | start | end
        $chars = $payload->config['chars'] ?? " \t\n\r\0\x0B";

        $result = match ($type) {
            'start' => ltrim($string, $chars),
            'end' => rtrim($string, $chars),
            'both' => trim($string, $chars),
            default => trim($string, $chars),
        };

        return [
            'result' => $result,
            'removed' => strlen($string) - strlen($result),
        ];
    }

    /**
     * Get substring
     */
    private function substring(NodePayload $payload): array
    {
        $string = $payload->config['string'] ?? '';
        $start = (int) ($payload->config['start'] ?? 0);
        $length = $payload->config['length'] ?? null;

        if ($length !== null) {
            $result = substr($string, $start, $length);
        } else {
            $result = substr($string, $start);
        }

        return [
            'result' => $result,
            'length' => strlen($result),
        ];
    }

    /**
     * Template rendering with variable substitution
     */
    private function template(NodePayload $payload): array
    {
        $template = $payload->config['template'] ?? '';
        $variables = $payload->config['variables'] ?? [];
        $syntax = $payload->config['syntax'] ?? 'mustache'; // mustache {{var}} | bracket {var}

        if (empty($template)) {
            throw new \InvalidArgumentException('Template is required');
        }

        $result = $template;

        foreach ($variables as $key => $value) {
            $placeholder = $syntax === 'mustache' ? "{{{$key}}}" : "{{$key}}";
            $result = str_replace($placeholder, $value, $result);
        }

        return [
            'result' => $result,
            'variables_used' => count($variables),
        ];
    }

    /**
     * String length and info
     */
    private function length(NodePayload $payload): array
    {
        $string = $payload->config['string'] ?? '';

        return [
            'length' => strlen($string),
            'word_count' => str_word_count($string),
            'line_count' => substr_count($string, "\n") + 1,
            'character_count' => mb_strlen($string),
        ];
    }
}
