<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;
use Carbon\Carbon;

/**
 * DataNode — all 15 required data-transformation operations in one class.
 *
 * Operation is selected via config['operation']. Each sub-method receives
 * the full NodePayload and returns an array that becomes the node output.
 *
 * Operations:
 *   json_transform    — pick, omit, get path, set path, merge, flatten, stringify, parse
 *   date_time         — parse, format, add, subtract, diff, now
 *   math              — arithmetic, rounding, min/max on scalars or item fields
 *   string            — case, trim, pad, split, join, replace, regex, slugify, truncate
 *   crypto            — hash (md5/sha1/sha256/sha512), hmac, base64 encode/decode
 *   xml               — parse XML→array, build array→XML
 *   csv               — parse CSV string→items, items→CSV string
 *   html_extract      — extract text/attributes via simple CSS-like selectors (DOMXPath)
 *   rename_keys       — bulk-rename fields across items
 *   remove_duplicates — deduplicate items by a key field
 *   sort              — sort items by one or more fields
 *   limit             — take the first N items
 *   summarize         — group-by + aggregate (sum, count, avg, min, max)
 *   filter            — keep/discard items by field conditions
 *   compare_datasets  — diff two item arrays by key → added, removed, modified, unchanged
 */
class DataNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'DATA_ERROR';
    }

    protected function operations(): array
    {
        return [
            'json_transform' => $this->jsonTransform(...),
            'date_time' => $this->dateTime(...),
            'math' => $this->math(...),
            'string' => $this->string(...),
            'crypto' => $this->crypto(...),
            'xml' => $this->xml(...),
            'csv' => $this->csv(...),
            'html_extract' => $this->htmlExtract(...),
            'rename_keys' => $this->renameKeys(...),
            'remove_duplicates' => $this->removeDuplicates(...),
            'sort' => $this->sort(...),
            'limit' => $this->limit(...),
            'summarize' => $this->summarize(...),
            'filter' => $this->filter(...),
            'compare_datasets' => $this->compareDatasets(...),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // JSON TRANSFORM
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function jsonTransform(NodeInput $payload): array
    {
        $action = $payload->config['action'] ?? 'pick';
        $data = $payload->inputData['data'] ?? $payload->inputData;

        return match ($action) {
            'pick' => [
                'data' => array_intersect_key(
                    is_array($data) ? $data : [],
                    array_flip((array) ($payload->config['keys'] ?? [])),
                ),
            ],
            'omit' => [
                'data' => array_diff_key(
                    is_array($data) ? $data : [],
                    array_flip((array) ($payload->config['keys'] ?? [])),
                ),
            ],
            'get_path' => [
                'value' => data_get($data, $payload->config['path'] ?? ''),
            ],
            'set_path' => (function () use ($data, $payload): array {
                $result = is_array($data) ? $data : [];
                data_set($result, $payload->config['path'] ?? '', $payload->config['value'] ?? null);

                return ['data' => $result];
            })(),
            'merge' => [
                'data' => array_merge(
                    is_array($data) ? $data : [],
                    is_array($payload->config['with'] ?? null) ? $payload->config['with'] : [],
                ),
            ],
            'flatten' => [
                'data' => array_merge(...array_map(
                    fn ($v) => is_array($v) ? $v : [$v],
                    is_array($data) ? $data : [],
                )),
            ],
            'stringify' => [
                'json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
            'parse' => [
                'data' => json_decode(
                    is_string($data) ? $data : ($payload->inputData['json'] ?? '{}'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
            ],
            default => throw new \InvalidArgumentException("Unknown json_transform action: {$action}"),
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // DATE & TIME
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function dateTime(NodeInput $payload): array
    {
        $action = $payload->config['action'] ?? 'now';
        $format = $payload->config['format'] ?? 'Y-m-d H:i:s';
        $timezone = $payload->config['timezone'] ?? 'UTC';
        $inputDate = $payload->inputData['date'] ?? $payload->config['date'] ?? null;

        $date = $inputDate ? Carbon::parse($inputDate, $timezone) : Carbon::now($timezone);

        return match ($action) {
            'now' => [
                'date' => Carbon::now($timezone)->format($format),
                'timestamp' => Carbon::now($timezone)->timestamp,
                'iso8601' => Carbon::now($timezone)->toIso8601String(),
            ],
            'format' => [
                'date' => $date->format($format),
                'timestamp' => $date->timestamp,
                'iso8601' => $date->toIso8601String(),
            ],
            'parse' => [
                'year' => $date->year,
                'month' => $date->month,
                'day' => $date->day,
                'hour' => $date->hour,
                'minute' => $date->minute,
                'second' => $date->second,
                'day_of_week' => $date->dayOfWeek,
                'timestamp' => $date->timestamp,
                'formatted' => $date->format($format),
            ],
            'add' => (function () use ($date, $payload, $format): array {
                $amount = (int) ($payload->config['amount'] ?? 1);
                $unit = $payload->config['unit'] ?? 'days';
                $result = $date->add($amount, $unit);

                return ['date' => $result->format($format), 'timestamp' => $result->timestamp, 'iso8601' => $result->toIso8601String()];
            })(),
            'subtract' => (function () use ($date, $payload, $format): array {
                $amount = (int) ($payload->config['amount'] ?? 1);
                $unit = $payload->config['unit'] ?? 'days';
                $result = $date->sub($amount, $unit);

                return ['date' => $result->format($format), 'timestamp' => $result->timestamp, 'iso8601' => $result->toIso8601String()];
            })(),
            'diff' => (function () use ($date, $payload, $timezone): array {
                $other = Carbon::parse($payload->inputData['date_b'] ?? $payload->config['date_b'] ?? 'now', $timezone);
                $unit = $payload->config['unit'] ?? 'days';

                return [
                    'diff' => $date->diffIn($unit, $other),
                    'human' => $date->diffForHumans($other),
                ];
            })(),
            default => throw new \InvalidArgumentException("Unknown date_time action: {$action}"),
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // MATH
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function math(NodeInput $payload): array
    {
        $action = $payload->config['action'] ?? 'add';
        $a = (float) ($payload->inputData['a'] ?? $payload->config['a'] ?? 0);
        $b = (float) ($payload->inputData['b'] ?? $payload->config['b'] ?? 0);
        $precision = (int) ($payload->config['precision'] ?? 10);
        $field = $payload->config['field'] ?? null;
        $items = $payload->inputData['items'] ?? [];

        if ($field && $action === 'sum_field') {
            return ['result' => array_sum(array_column($items, $field))];
        }

        if ($field && $action === 'avg_field') {
            $vals = array_column($items, $field);

            return ['result' => count($vals) > 0 ? array_sum($vals) / count($vals) : 0];
        }

        $result = match ($action) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b != 0 ? $a / $b : throw new \DivisionByZeroError('Division by zero'),
            'modulo' => $b != 0 ? fmod($a, $b) : throw new \DivisionByZeroError('Modulo by zero'),
            'power' => $b >= 0 ? $a ** $b : 1 / ($a ** abs($b)),
            'round' => round($a, (int) ($payload->config['decimals'] ?? 0)),
            'floor' => floor($a),
            'ceil' => ceil($a),
            'abs' => abs($a),
            'min' => min($a, $b),
            'max' => max($a, $b),
            'percentage' => $b != 0 ? ($a / $b) * 100 : 0,
            default => throw new \InvalidArgumentException("Unknown math action: {$action}"),
        };

        return ['result' => round($result, $precision)];
    }

    // ─────────────────────────────────────────────────────────────────────
    // STRING
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function string(NodeInput $payload): array
    {
        $action = $payload->config['action'] ?? 'trim';
        $input = (string) ($payload->inputData['value'] ?? $payload->inputData['string'] ?? $payload->config['value'] ?? '');

        return match ($action) {
            'upper' => ['result' => strtoupper($input)],
            'lower' => ['result' => strtolower($input)],
            'title' => ['result' => ucwords(strtolower($input))],
            'trim' => ['result' => trim($input, (string) ($payload->config['characters'] ?? " \t\n\r\0\x0B"))],
            'ltrim' => ['result' => ltrim($input, (string) ($payload->config['characters'] ?? " \t\n\r\0\x0B"))],
            'rtrim' => ['result' => rtrim($input, (string) ($payload->config['characters'] ?? " \t\n\r\0\x0B"))],
            'pad_left' => ['result' => str_pad($input, (int) ($payload->config['length'] ?? strlen($input)), (string) ($payload->config['pad_string'] ?? ' '), STR_PAD_LEFT)],
            'pad_right' => ['result' => str_pad($input, (int) ($payload->config['length'] ?? strlen($input)), (string) ($payload->config['pad_string'] ?? ' '), STR_PAD_RIGHT)],
            'split' => ['result' => explode((string) ($payload->config['delimiter'] ?? ','), $input)],
            'join' => ['result' => implode((string) ($payload->config['delimiter'] ?? ','), (array) ($payload->inputData['items'] ?? []))],
            'replace' => ['result' => str_replace((string) ($payload->config['search'] ?? ''), (string) ($payload->config['replace'] ?? ''), $input)],
            'regex_replace' => ['result' => preg_replace((string) ($payload->config['pattern'] ?? '//'), (string) ($payload->config['replace'] ?? ''), $input)],
            'regex_match' => (function () use ($input, $payload): array {
                preg_match_all((string) ($payload->config['pattern'] ?? '//'), $input, $matches);

                return ['matches' => $matches[0], 'groups' => array_slice($matches, 1)];
            })(),
            'contains' => ['result' => str_contains($input, (string) ($payload->config['search'] ?? ''))],
            'starts_with' => ['result' => str_starts_with($input, (string) ($payload->config['search'] ?? ''))],
            'ends_with' => ['result' => str_ends_with($input, (string) ($payload->config['search'] ?? ''))],
            'length' => ['result' => strlen($input)],
            'reverse' => ['result' => strrev($input)],
            'repeat' => ['result' => str_repeat($input, max(1, (int) ($payload->config['times'] ?? 1)))],
            'truncate' => (function () use ($input, $payload): array {
                $len = (int) ($payload->config['length'] ?? 100);
                $suffix = (string) ($payload->config['suffix'] ?? '...');

                return ['result' => strlen($input) > $len ? substr($input, 0, $len).$suffix : $input];
            })(),
            'slug' => ['result' => \Illuminate\Support\Str::slug($input)],
            'camel' => ['result' => \Illuminate\Support\Str::camel($input)],
            'snake' => ['result' => \Illuminate\Support\Str::snake($input)],
            'kebab' => ['result' => \Illuminate\Support\Str::kebab($input)],
            'studly' => ['result' => \Illuminate\Support\Str::studly($input)],
            'word_count' => ['result' => str_word_count($input)],
            'substr' => ['result' => substr($input, (int) ($payload->config['start'] ?? 0), $payload->config['length'] ?? null)],
            default => throw new \InvalidArgumentException("Unknown string action: {$action}"),
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRYPTO
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function crypto(NodeInput $payload): array
    {
        $action = $payload->config['action'] ?? 'sha256';
        $input = (string) ($payload->inputData['value'] ?? $payload->config['value'] ?? '');
        $secret = (string) ($payload->config['secret'] ?? '');

        return match ($action) {
            'md5' => ['hash' => md5($input), 'algorithm' => 'md5'],
            'sha1' => ['hash' => sha1($input), 'algorithm' => 'sha1'],
            'sha256' => ['hash' => hash('sha256', $input), 'algorithm' => 'sha256'],
            'sha512' => ['hash' => hash('sha512', $input), 'algorithm' => 'sha512'],
            'hmac_sha256' => ['hash' => hash_hmac('sha256', $input, $secret), 'algorithm' => 'hmac-sha256'],
            'hmac_sha512' => ['hash' => hash_hmac('sha512', $input, $secret), 'algorithm' => 'hmac-sha512'],
            'base64_encode' => ['result' => base64_encode($input)],
            'base64_decode' => ['result' => base64_decode($input, true) ?: throw new \RuntimeException('Invalid base64 input')],
            'base64_url_encode' => ['result' => rtrim(strtr(base64_encode($input), '+/', '-_'), '=')],
            'random_bytes' => (function () use ($payload): array {
                $length = max(1, (int) ($payload->config['length'] ?? 32));

                return ['hex' => bin2hex(random_bytes($length)), 'length' => $length];
            })(),
            'uuid' => ['uuid' => (string) \Illuminate\Support\Str::uuid()],
            default => throw new \InvalidArgumentException("Unknown crypto action: {$action}"),
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // XML
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function xml(NodeInput $payload): array
    {
        $action = $payload->config['action'] ?? 'parse';

        if ($action === 'parse') {
            $xmlString = (string) ($payload->inputData['xml'] ?? $payload->config['xml'] ?? '');
            $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml === false) {
                throw new \RuntimeException('Failed to parse XML: '.implode(', ', array_map(fn ($e) => $e->message, libxml_get_errors())));
            }

            return ['data' => json_decode(json_encode($xml), true)];
        }

        if ($action === 'build') {
            $data = $payload->inputData['data'] ?? $payload->config['data'] ?? [];
            $rootElement = (string) ($payload->config['root'] ?? 'root');

            $xml = new \SimpleXMLElement("<{$rootElement}/>");
            $this->arrayToXml(is_array($data) ? $data : [], $xml);

            return ['xml' => $xml->asXML()];
        }

        throw new \InvalidArgumentException("Unknown xml action: {$action}");
    }

    private function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            $key = is_int($key) ? 'item' : $key;
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->arrayToXml($value, $child);
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // CSV
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function csv(NodeInput $payload): array
    {
        $action = $payload->config['action'] ?? 'parse';
        $delimiter = (string) ($payload->config['delimiter'] ?? ',');
        $enclosure = (string) ($payload->config['enclosure'] ?? '"');

        if ($action === 'parse') {
            $csvString = (string) ($payload->inputData['csv'] ?? $payload->config['csv'] ?? '');
            $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $csvString)));
            $hasHeader = (bool) ($payload->config['header'] ?? true);

            $rows = array_map(fn ($line) => str_getcsv($line, $delimiter, $enclosure), array_values($lines));

            if ($hasHeader && count($rows) > 0) {
                $headers = array_shift($rows);

                return [
                    'items' => array_map(fn ($row) => array_combine($headers, array_pad($row, count($headers), null)), $rows),
                    'headers' => $headers,
                    'count' => count($rows),
                ];
            }

            return ['items' => $rows, 'count' => count($rows)];
        }

        if ($action === 'build') {
            $items = (array) ($payload->inputData['items'] ?? []);
            $includeHeader = (bool) ($payload->config['header'] ?? true);

            if (empty($items)) {
                return ['csv' => ''];
            }

            $output = fopen('php://temp', 'r+');
            if ($includeHeader && is_array($items[0])) {
                fputcsv($output, array_keys($items[0]), $delimiter, $enclosure);
            }

            foreach ($items as $row) {
                fputcsv($output, is_array($row) ? array_values($row) : [$row], $delimiter, $enclosure);
            }

            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);

            return ['csv' => $csv];
        }

        throw new \InvalidArgumentException("Unknown csv action: {$action}");
    }

    // ─────────────────────────────────────────────────────────────────────
    // HTML EXTRACT
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Extract data from HTML using simple CSS-like selectors via DOMXPath.
     *
     * Supported selector forms:
     *   tag           → //tag
     *   .class        → //*[contains(@class,"class")]
     *   #id           → //*[@id="id"]
     *   tag.class     → //tag[contains(@class,"class")]
     *   [attr]        → //*[@attr]
     *   [attr=value]  → //*[@attr="value"]
     *
     * @return array<string, mixed>
     */
    private function htmlExtract(NodeInput $payload): array
    {
        $html = (string) ($payload->inputData['html'] ?? $payload->config['html'] ?? '');
        $selectors = (array) ($payload->config['selectors'] ?? []);
        $attribute = (string) ($payload->config['attribute'] ?? 'text');

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $results = [];

        foreach ($selectors as $selectorConfig) {
            $selector = is_array($selectorConfig) ? ($selectorConfig['selector'] ?? '') : $selectorConfig;
            $attr = is_array($selectorConfig) ? ($selectorConfig['attribute'] ?? $attribute) : $attribute;
            $alias = is_array($selectorConfig) ? ($selectorConfig['alias'] ?? $selector) : $selector;

            $xpathQuery = $this->cssToXpath($selector);
            $nodes = $xpath->query($xpathQuery);
            $extracted = [];

            if ($nodes !== false) {
                foreach ($nodes as $node) {
                    $extracted[] = $attr === 'text'
                        ? $node->textContent
                        : ($node->getAttribute($attr) ?: $node->textContent);
                }
            }

            $results[$alias] = $extracted;
        }

        return ['results' => $results];
    }

    private function cssToXpath(string $selector): string
    {
        $selector = trim($selector);

        if (str_starts_with($selector, '#')) {
            return '//*[@id="'.substr($selector, 1).'"]';
        }

        if (str_starts_with($selector, '.')) {
            return '//*[contains(concat(" ",normalize-space(@class)," ")," '.substr($selector, 1).' ")]';
        }

        if (preg_match('/^(\w+)\.(\S+)$/', $selector, $m)) {
            return "//{$m[1]}[contains(concat(\" \",normalize-space(@class),\" \"),\" {$m[2]} \")]";
        }

        if (preg_match('/^\[(\w+)=["\'](.*?)["\']\]$/', $selector, $m)) {
            return "//*[@{$m[1]}=\"{$m[2]}\"]";
        }

        if (preg_match('/^\[(\w+)\]$/', $selector, $m)) {
            return "//*[@{$m[1]}]";
        }

        return '//'.$selector;
    }

    // ─────────────────────────────────────────────────────────────────────
    // RENAME KEYS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function renameKeys(NodeInput $payload): array
    {
        $mappings = (array) ($payload->config['mappings'] ?? []);
        $items = (array) ($payload->inputData['items'] ?? [$payload->inputData]);
        $keepOriginal = (bool) ($payload->config['keep_original'] ?? false);

        $renamed = array_map(function (array $item) use ($mappings, $keepOriginal): array {
            foreach ($mappings as $oldKey => $newKey) {
                if (array_key_exists($oldKey, $item)) {
                    $item[$newKey] = $item[$oldKey];
                    if (! $keepOriginal) {
                        unset($item[$oldKey]);
                    }
                }
            }

            return $item;
        }, $items);

        return ['items' => $renamed, 'count' => count($renamed)];
    }

    // ─────────────────────────────────────────────────────────────────────
    // REMOVE DUPLICATES
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function removeDuplicates(NodeInput $payload): array
    {
        $key = (string) ($payload->config['key'] ?? '');
        $items = (array) ($payload->inputData['items'] ?? []);
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $keyValue = $key !== '' ? ($item[$key] ?? null) : json_encode($item);
            $fingerprint = is_scalar($keyValue) ? (string) $keyValue : json_encode($keyValue);

            if (! isset($seen[$fingerprint])) {
                $seen[$fingerprint] = true;
                $unique[] = $item;
            }
        }

        return [
            'items' => $unique,
            'count' => count($unique),
            'removed' => count($items) - count($unique),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // SORT
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function sort(NodeInput $payload): array
    {
        $items = (array) ($payload->inputData['items'] ?? []);
        $sortFields = (array) ($payload->config['sort_fields'] ?? []);

        if (empty($sortFields) && isset($payload->config['key'])) {
            $sortFields = [['key' => $payload->config['key'], 'direction' => $payload->config['direction'] ?? 'asc']];
        }

        usort($items, function (array $a, array $b) use ($sortFields): int {
            foreach ($sortFields as $sortField) {
                $key = $sortField['key'] ?? '';
                $direction = strtolower($sortField['direction'] ?? 'asc') === 'desc' ? -1 : 1;
                $type = $sortField['type'] ?? 'string';

                $valA = $a[$key] ?? null;
                $valB = $b[$key] ?? null;

                $cmp = $type === 'number'
                    ? ((float) $valA <=> (float) $valB)
                    : strcmp((string) $valA, (string) $valB);

                if ($cmp !== 0) {
                    return $cmp * $direction;
                }
            }

            return 0;
        });

        return ['items' => array_values($items), 'count' => count($items)];
    }

    // ─────────────────────────────────────────────────────────────────────
    // LIMIT
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function limit(NodeInput $payload): array
    {
        $items = (array) ($payload->inputData['items'] ?? []);
        $count = max(0, (int) ($payload->config['count'] ?? 10));
        $offset = max(0, (int) ($payload->config['offset'] ?? 0));

        $sliced = array_slice($items, $offset, $count);

        return [
            'items' => $sliced,
            'count' => count($sliced),
            'total' => count($items),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // SUMMARIZE
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Group items by a field and apply aggregate functions (sum, count, avg, min, max).
     *
     * config:
     *   group_by:     field name to group by (omit for a single global aggregate)
     *   aggregations: [{field, function, alias}]
     *
     * @return array<string, mixed>
     */
    private function summarize(NodeInput $payload): array
    {
        $items = (array) ($payload->inputData['items'] ?? []);
        $groupBy = $payload->config['group_by'] ?? null;
        $aggregations = (array) ($payload->config['aggregations'] ?? []);

        if ($groupBy !== null) {
            $groups = [];
            foreach ($items as $item) {
                $groupKey = (string) ($item[$groupBy] ?? '__null__');
                $groups[$groupKey][] = $item;
            }

            $result = [];
            foreach ($groups as $groupKey => $groupItems) {
                $row = [$groupBy => $groupKey === '__null__' ? null : $groupKey];
                foreach ($aggregations as $agg) {
                    $row[$agg['alias'] ?? $agg['field']] = $this->applyAggregation($groupItems, $agg);
                }
                $result[] = $row;
            }

            return ['items' => $result, 'groups' => count($result)];
        }

        $row = [];
        foreach ($aggregations as $agg) {
            $row[$agg['alias'] ?? $agg['field']] = $this->applyAggregation($items, $agg);
        }

        return ['summary' => $row, 'total_items' => count($items)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $agg
     */
    private function applyAggregation(array $items, array $agg): mixed
    {
        $field = $agg['field'] ?? '';
        $function = strtolower($agg['function'] ?? 'count');
        $values = $field !== '' ? array_column($items, $field) : [];
        $numericValues = array_filter($values, 'is_numeric');

        return match ($function) {
            'count' => count($items),
            'count_distinct' => count(array_unique(array_map('strval', $values))),
            'sum' => array_sum($numericValues),
            'avg' => count($numericValues) > 0 ? array_sum($numericValues) / count($numericValues) : null,
            'min' => count($numericValues) > 0 ? min($numericValues) : null,
            'max' => count($numericValues) > 0 ? max($numericValues) : null,
            'first' => $values[0] ?? null,
            'last' => $values[count($values) - 1] ?? null,
            default => null,
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // FILTER
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function filter(NodeInput $payload): array
    {
        $items = (array) ($payload->inputData['items'] ?? []);
        $conditions = (array) ($payload->config['conditions'] ?? []);
        $mode = $payload->config['mode'] ?? 'AND';

        $filtered = array_values(array_filter($items, function (array $item) use ($conditions, $mode): bool {
            $results = array_map(fn (array $c) => $this->evaluateCondition($item, $c), $conditions);

            return $mode === 'OR'
                ? in_array(true, $results, true)
                : ! in_array(false, $results, true);
        }));

        return ['items' => $filtered, 'count' => count($filtered), 'excluded' => count($items) - count($filtered)];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $condition
     */
    private function evaluateCondition(array $item, array $condition): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;
        $fieldValue = data_get($item, $field);

        return match ($operator) {
            'equals', '==' => $fieldValue == $value,
            'not_equals', '!=' => $fieldValue != $value,
            'strict_equals', '===' => $fieldValue === $value,
            'contains' => is_string($fieldValue) && str_contains($fieldValue, (string) $value),
            'not_contains' => is_string($fieldValue) && ! str_contains($fieldValue, (string) $value),
            'starts_with' => is_string($fieldValue) && str_starts_with($fieldValue, (string) $value),
            'ends_with' => is_string($fieldValue) && str_ends_with($fieldValue, (string) $value),
            'gt', '>' => $fieldValue > $value,
            'gte', '>=' => $fieldValue >= $value,
            'lt', '<' => $fieldValue < $value,
            'lte', '<=' => $fieldValue <= $value,
            'is_null' => $fieldValue === null,
            'is_not_null' => $fieldValue !== null,
            'is_empty' => empty($fieldValue),
            'is_not_empty' => ! empty($fieldValue),
            'in' => in_array($fieldValue, (array) $value),
            'not_in' => ! in_array($fieldValue, (array) $value),
            'regex' => is_string($fieldValue) && preg_match((string) $value, $fieldValue) === 1,
            default => false,
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // COMPARE DATASETS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Diff two item arrays by a key field.
     *
     * Output:
     *   added    — items in B that are not in A (by key)
     *   removed  — items in A that are not in B (by key)
     *   modified — items in both A and B but with different field values
     *   unchanged — items in both A and B with identical values
     *
     * @return array<string, mixed>
     */
    private function compareDatasets(NodeInput $payload): array
    {
        $key = (string) ($payload->config['key'] ?? 'id');
        $aItems = (array) ($payload->inputData['a_items'] ?? []);
        $bItems = (array) ($payload->inputData['b_items'] ?? []);

        $aIndexed = [];
        foreach ($aItems as $item) {
            $k = (string) ($item[$key] ?? json_encode($item));
            $aIndexed[$k] = $item;
        }

        $bIndexed = [];
        foreach ($bItems as $item) {
            $k = (string) ($item[$key] ?? json_encode($item));
            $bIndexed[$k] = $item;
        }

        $added = array_values(array_filter($bIndexed, fn ($k) => ! isset($aIndexed[$k]), ARRAY_FILTER_USE_KEY));
        $removed = array_values(array_filter($aIndexed, fn ($k) => ! isset($bIndexed[$k]), ARRAY_FILTER_USE_KEY));
        $modified = [];
        $unchanged = [];

        foreach (array_intersect_key($aIndexed, $bIndexed) as $k => $aItem) {
            $bItem = $bIndexed[$k];
            if ($aItem !== $bItem) {
                $modified[] = ['key' => $k, 'before' => $aItem, 'after' => $bItem];
            } else {
                $unchanged[] = $aItem;
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
            'unchanged' => $unchanged,
            'summary' => [
                'added' => count($added),
                'removed' => count($removed),
                'modified' => count($modified),
                'unchanged' => count($unchanged),
            ],
        ];
    }
}
