<?php

namespace App\Engine\Graph;

class ExpressionResolver
{
    public function compile(string $template): array
    {
        if (! str_contains($template, '{{')) {
            return [['type' => 'literal', 'value' => $template]];
        }

        $tokens = [];
        $remaining = $template;

        while (preg_match('/\{\{\s*(.+?)\s*\}\}/', $remaining, $match, PREG_OFFSET_CAPTURE)) {
            $matchStart = $match[0][1];
            $fullMatch = $match[0][0];
            $expression = trim($match[1][0]);

            if ($matchStart > 0) {
                $tokens[] = ['type' => 'literal', 'value' => substr($remaining, 0, $matchStart)];
            }

            $tokens[] = $this->parseExpression($expression);

            $remaining = substr($remaining, $matchStart + strlen($fullMatch));
        }

        if ($remaining !== '') {
            $tokens[] = ['type' => 'literal', 'value' => $remaining];
        }

        return $tokens;
    }

    public function resolve(array $tokens, array $context): mixed
    {
        if (count($tokens) === 1 && $tokens[0]['type'] !== 'literal') {
            return $this->resolveToken($tokens[0], $context);
        }

        $result = '';
        foreach ($tokens as $token) {
            $value = $token['type'] === 'literal'
                ? $token['value']
                : $this->resolveToken($token, $context);

            $result .= is_array($value) ? json_encode($value) : (string) $value;
        }

        return $result;
    }

    public function evaluate(string $template, array $context): mixed
    {
        return $this->resolve($this->compile($template), $context);
    }

    public function compileConfig(array $config): array
    {
        $compiled = [];

        foreach ($config as $key => $value) {
            if (is_string($value) && str_contains($value, '{{')) {
                $compiled[$key] = ['__expr' => true, 'tokens' => $this->compile($value)];
            } elseif (is_array($value)) {
                $compiled[$key] = $this->compileConfig($value);
            } else {
                $compiled[$key] = $value;
            }
        }

        return $compiled;
    }

    public function resolveConfig(array $config, array $context): array
    {
        $resolved = [];

        foreach ($config as $key => $value) {
            if (is_array($value) && ($value['__expr'] ?? false)) {
                $resolved[$key] = $this->resolve($value['tokens'], $context);
            } elseif (is_array($value)) {
                $resolved[$key] = $this->resolveConfig($value, $context);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    public function hasExpressions(array $config): bool
    {
        foreach ($config as $value) {
            if (is_array($value)) {
                if (isset($value['__expr'])) {
                    return true;
                }
                if ($this->hasExpressions($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function extractNodeDependencies(string $template): array
    {
        $tokens = $this->compile($template);
        $nodes = [];

        foreach ($tokens as $token) {
            if (($token['source'] ?? null) === 'nodes' && isset($token['node'])) {
                $nodes[] = $token['node'];
            }
        }

        return array_values(array_unique($nodes));
    }

    private function parseExpression(string $expression): array
    {
        $expression = ltrim($expression, '$');
        $segments = explode('.', $expression);
        $source = array_shift($segments);

        return match ($source) {
            'nodes' => [
                'type' => 'path',
                'source' => 'nodes',
                'node' => array_shift($segments) ?? '',
                'path' => $segments,
            ],
            'trigger', 'vars', 'env', 'execution', 'loop' => [
                'type' => 'path',
                'source' => $source,
                'path' => $segments,
            ],
            default => [
                'type' => 'path',
                'source' => 'vars',
                'path' => array_merge([$source], $segments),
            ],
        };
    }

    private function resolveToken(array $token, array $context): mixed
    {
        $source = $token['source'];
        $path = $token['path'];

        $data = match ($source) {
            'nodes' => $context['nodes'][$token['node']] ?? null,
            'trigger' => $context['trigger'] ?? [],
            'vars' => $context['vars'] ?? [],
            'env' => $context['env'] ?? [],
            'execution' => $context['execution'] ?? [],
            'loop' => $context['loop'] ?? [],
            default => null,
        };

        if ($data === null || empty($path)) {
            return $data;
        }

        return data_get($data, implode('.', $path));
    }
}
