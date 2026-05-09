<?php

namespace Database\Seeders;

use App\Models\Node;
use App\Models\NodeCategory;
use Illuminate\Database\Seeder;

class NodeSeeder extends Seeder
{
    /**
     * Node types to disable. Add a type string here to seed it as inactive.
     *
     * @var list<string>
     */
    private const DISABLED_NODES = [
        // 'ai.text_classifier',
        // 'ai.summarizer',
        // 'storage.read_file',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = NodeCategory::query()->pluck('id', 'slug');

        $nodes = [
            // ── Triggers ──────────────────────────────────────────────
            [
                'category' => 'triggers',
                'type' => 'trigger.webhook',
                'name' => 'Webhook',
                'description' => 'Starts the workflow when an HTTP request is received.',
                'icon' => 'bolt',
                'color' => '#F59E0B',
                'node_kind' => 'trigger',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'http_method' => ['type' => 'string', 'enum' => ['GET', 'POST', 'PUT', 'DELETE'], 'default' => 'POST'],
                        'path' => ['type' => 'string', 'description' => 'Custom webhook path'],
                        'authentication' => ['type' => 'string', 'enum' => ['none', 'header', 'basic'], 'default' => 'none'],
                    ],
                    'required' => ['http_method'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'headers' => ['type' => 'object'],
                        'body' => ['type' => 'object'],
                        'query' => ['type' => 'object'],
                    ],
                ],
            ],
            [
                'category' => 'triggers',
                'type' => 'trigger.schedule',
                'name' => 'Schedule',
                'description' => 'Starts the workflow on a recurring cron schedule.',
                'icon' => 'clock',
                'color' => '#F59E0B',
                'node_kind' => 'trigger',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'cron' => ['type' => 'string', 'description' => 'Cron expression (e.g. */5 * * * *)'],
                        'timezone' => ['type' => 'string', 'default' => 'UTC'],
                    ],
                    'required' => ['cron'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'triggered_at' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                ],
            ],
            [
                'category' => 'triggers',
                'type' => 'trigger.manual',
                'name' => 'Manual Trigger',
                'description' => 'Starts the workflow manually by the user.',
                'icon' => 'hand-raised',
                'color' => '#F59E0B',
                'node_kind' => 'trigger',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'input_fields' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'type' => ['type' => 'string', 'enum' => ['string', 'number', 'boolean']],
                                ],
                            ],
                        ],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'input' => ['type' => 'object'],
                    ],
                ],
            ],

            // ── AI ────────────────────────────────────────────────────
            [
                'category' => 'ai',
                'type' => 'ai.llm',
                'name' => 'LLM Prompt',
                'description' => 'Send a prompt to a large language model and receive a completion.',
                'icon' => 'cpu-chip',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'credential_type' => 'openai',
                'is_premium' => true,
                'cost_hint_usd' => 0.0020,
                'latency_hint_ms' => 3000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini', 'groq', 'xai', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'gpt-4o-mini'],
                        'system_prompt' => ['type' => 'string'],
                        'temperature' => ['type' => 'number', 'minimum' => 0, 'maximum' => 2, 'default' => 0.7],
                        'max_tokens' => ['type' => 'integer', 'default' => 1024],
                    ],
                    'required' => ['model'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt' => ['type' => 'string'],
                    ],
                    'required' => ['prompt'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                        'usage' => ['type' => 'object', 'properties' => [
                            'prompt_tokens' => ['type' => 'integer'],
                            'completion_tokens' => ['type' => 'integer'],
                        ]],
                    ],
                ],
            ],
            [
                'category' => 'ai',
                'type' => 'ai.text_classifier',
                'name' => 'Text Classifier',
                'description' => 'Classify text into predefined categories using AI.',
                'icon' => 'tag',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'credential_type' => 'openai',
                'is_premium' => true,
                'cost_hint_usd' => 0.0010,
                'latency_hint_ms' => 2000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'categories' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'List of classification labels'],
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini', 'groq', 'xai', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'gpt-4o-mini'],
                    ],
                    'required' => ['categories'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                    ],
                    'required' => ['text'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => ['type' => 'string'],
                        'confidence' => ['type' => 'number'],
                    ],
                ],
            ],
            [
                'category' => 'ai',
                'type' => 'ai.summarizer',
                'name' => 'Summarizer',
                'description' => 'Summarize long text into concise bullet points or a paragraph.',
                'icon' => 'document-text',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'credential_type' => 'openai',
                'is_premium' => true,
                'cost_hint_usd' => 0.0015,
                'latency_hint_ms' => 2500,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'format' => ['type' => 'string', 'enum' => ['paragraph', 'bullets'], 'default' => 'paragraph'],
                        'max_length' => ['type' => 'integer', 'default' => 200],
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini', 'groq', 'xai', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'gpt-4o-mini'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                    ],
                    'required' => ['text'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'summary' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'category' => 'ai',
                'type' => 'ai.sentiment',
                'name' => 'Sentiment Analysis',
                'description' => 'Analyze the sentiment of text — returns positive/negative/neutral with a confidence score and detected emotions.',
                'icon' => 'heart',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'is_premium' => true,
                'cost_hint_usd' => 0.0010,
                'latency_hint_ms' => 2000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini', 'groq', 'xai', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'gpt-4o-mini'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                    ],
                    'required' => ['text'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'sentiment' => ['type' => 'string'],
                        'score' => ['type' => 'number'],
                        'emotions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
            [
                'category' => 'ai',
                'type' => 'ai.embeddings',
                'name' => 'Embeddings',
                'description' => 'Generate vector embeddings for text — useful for semantic search, clustering, and RAG pipelines.',
                'icon' => 'cube-transparent',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'is_premium' => true,
                'cost_hint_usd' => 0.0002,
                'latency_hint_ms' => 1000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'gemini', 'cohere', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'text-embedding-3-small'],
                        'dimensions' => ['type' => 'integer', 'default' => 1536],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                    ],
                    'required' => ['text'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'embeddings' => ['type' => 'array'],
                    ],
                ],
            ],
            [
                'category' => 'ai',
                'type' => 'ai.image_generation',
                'name' => 'Image Generation',
                'description' => 'Generate images from text prompts using AI (DALL·E, Gemini, etc.).',
                'icon' => 'photo',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'is_premium' => true,
                'cost_hint_usd' => 0.0400,
                'latency_hint_ms' => 10000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'gemini', 'xai'], 'default' => 'gemini'],
                        'model' => ['type' => 'string'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt' => ['type' => 'string'],
                    ],
                    'required' => ['prompt'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'images' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'image_count' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'category' => 'ai',
                'type' => 'ai.agent',
                'name' => 'AI Agent',
                'description' => 'Autonomous AI agent that decides which tools to use to complete a task. Configure a system prompt and select tools — the agent handles the rest.',
                'icon' => 'sparkles',
                'color' => '#7C3AED',
                'node_kind' => 'action',
                'is_premium' => true,
                'cost_hint_usd' => 0.0500,
                'latency_hint_ms' => 15000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => ['type' => 'string', 'enum' => ['openai', 'anthropic', 'gemini', 'groq', 'xai', 'mistral'], 'default' => 'openai'],
                        'model' => ['type' => 'string', 'default' => 'gpt-4o'],
                        'system_prompt' => ['type' => 'string'],
                        'max_steps' => ['type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 25],
                        'tools' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Node types the agent can use as tools (e.g. slack.send_message, http.request)'],
                        'temperature' => ['type' => 'number', 'default' => 0.7, 'minimum' => 0, 'maximum' => 2],
                    ],
                    'required' => ['system_prompt', 'tools'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt' => ['type' => 'string'],
                    ],
                    'required' => ['prompt'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'response' => ['type' => 'string'],
                        'provider' => ['type' => 'string'],
                        'model' => ['type' => 'string'],
                    ],
                ],
            ],

            // ── Flow Control ──────────────────────────────────────────
            [
                'category' => 'flow-control',
                'type' => 'flow.if',
                'name' => 'If / Else',
                'description' => 'Branch the workflow based on a condition.',
                'icon' => 'arrows-right-left',
                'color' => '#3B82F6',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'conditions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'field' => ['type' => 'string'],
                                    'operator' => ['type' => 'string', 'enum' => ['equals', 'not_equals', 'contains', 'gt', 'lt', 'gte', 'lte', 'is_empty', 'is_not_empty']],
                                    'value' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'combine' => ['type' => 'string', 'enum' => ['and', 'or'], 'default' => 'and'],
                    ],
                    'required' => ['conditions'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'branch' => ['type' => 'string', 'enum' => ['true', 'false']],
                    ],
                ],
            ],
            [
                'category' => 'flow-control',
                'type' => 'flow.switch',
                'name' => 'Switch',
                'description' => 'Route to different branches based on matching a value.',
                'icon' => 'queue-list',
                'color' => '#3B82F6',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'field' => ['type' => 'string', 'description' => 'The field to evaluate'],
                        'cases' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'value' => ['type' => 'string'],
                                    'label' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'has_default' => ['type' => 'boolean', 'default' => true],
                    ],
                    'required' => ['field', 'cases'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'matched_case' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'category' => 'flow-control',
                'type' => 'flow.loop',
                'name' => 'Loop',
                'description' => 'Advanced iterator with serial, parallel, and batched execution modes. Supports rate limiting, error handling, and break conditions.',
                'icon' => 'arrow-path',
                'color' => '#3B82F6',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'source' => [
                            'type' => 'string',
                            'default' => 'items',
                            'description' => 'Path to the array in input data (e.g. "items", "data.users")',
                        ],
                        'mode' => [
                            'type' => 'string',
                            'enum' => ['serial', 'parallel', 'batched'],
                            'default' => 'serial',
                            'description' => 'Execution mode: serial (one-by-one), parallel (concurrent), or batched (groups)',
                        ],
                        'batch_size' => [
                            'type' => 'integer',
                            'default' => 10,
                            'minimum' => 1,
                            'description' => 'Number of items per batch (only for batched mode)',
                        ],
                        'max_concurrency' => [
                            'type' => 'integer',
                            'default' => 5,
                            'minimum' => 1,
                            'maximum' => 50,
                            'description' => 'Maximum concurrent executions (only for parallel mode)',
                        ],
                        'max_iterations' => [
                            'type' => 'integer',
                            'default' => null,
                            'minimum' => 1,
                            'description' => 'Maximum number of items to process (null = unlimited)',
                        ],
                        'delay_ms' => [
                            'type' => 'integer',
                            'default' => 0,
                            'minimum' => 0,
                            'description' => 'Delay in milliseconds between iterations/batches (rate limiting)',
                        ],
                        'on_error' => [
                            'type' => 'string',
                            'enum' => ['stop', 'continue', 'fail_after_n'],
                            'default' => 'stop',
                            'description' => 'Error handling: stop (halt on first error), continue (skip failed items), fail_after_n (fail after N errors)',
                        ],
                        'fail_after_errors' => [
                            'type' => 'integer',
                            'default' => 3,
                            'minimum' => 1,
                            'description' => 'Number of errors before failing (only when on_error=fail_after_n)',
                        ],
                        'break_condition' => [
                            'type' => 'string',
                            'default' => null,
                            'description' => 'Expression to evaluate for breaking the loop early (e.g. "item.status == \'stop\'")',
                        ],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => [
                            'type' => 'array',
                            'description' => 'Array of items to iterate over',
                        ],
                    ],
                    'required' => ['items'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'item_count' => [
                            'type' => 'integer',
                            'description' => 'Total number of items to process',
                        ],
                        'mode' => [
                            'type' => 'string',
                            'description' => 'Execution mode used',
                        ],
                        'batch_size' => [
                            'type' => 'integer',
                            'description' => 'Batch size configuration',
                        ],
                    ],
                ],
            ],
            [
                'category' => 'flow-control',
                'type' => 'flow.delay',
                'name' => 'Delay',
                'description' => 'Pause the workflow for a specified amount of time.',
                'icon' => 'clock',
                'color' => '#3B82F6',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'duration' => ['type' => 'integer', 'description' => 'Duration in seconds'],
                        'unit' => ['type' => 'string', 'enum' => ['seconds', 'minutes', 'hours'], 'default' => 'seconds'],
                    ],
                    'required' => ['duration'],
                ],
            ],
            [
                'category' => 'flow-control',
                'type' => 'flow.merge',
                'name' => 'Merge',
                'description' => 'Merge multiple branches back into a single path.',
                'icon' => 'arrows-pointing-in',
                'color' => '#3B82F6',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'mode' => ['type' => 'string', 'enum' => ['wait_all', 'first'], 'default' => 'wait_all'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'inputs' => ['type' => 'array'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'merged' => ['type' => 'object'],
                    ],
                ],
            ],

            // ── Data ──────────────────────────────────────────────────
            [
                'category' => 'data',
                'type' => 'data.transform',
                'name' => 'Data Transform',
                'description' => 'Map, rename, or restructure data fields.',
                'icon' => 'adjustments-horizontal',
                'color' => '#10B981',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'mappings' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'source' => ['type' => 'string'],
                                    'target' => ['type' => 'string'],
                                    'transform' => ['type' => 'string', 'enum' => ['none', 'uppercase', 'lowercase', 'trim', 'to_number', 'to_string', 'to_boolean']],
                                ],
                            ],
                        ],
                    ],
                    'required' => ['mappings'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                    ],
                    'required' => ['data'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                    ],
                ],
            ],
            [
                'category' => 'data',
                'type' => 'data.filter',
                'name' => 'Filter',
                'description' => 'Filter items in an array based on conditions.',
                'icon' => 'funnel',
                'color' => '#10B981',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'conditions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'field' => ['type' => 'string'],
                                    'operator' => ['type' => 'string', 'enum' => ['equals', 'not_equals', 'contains', 'gt', 'lt']],
                                    'value' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                    'required' => ['conditions'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array'],
                    ],
                    'required' => ['items'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array'],
                        'count' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'category' => 'data',
                'type' => 'data.aggregate',
                'name' => 'Aggregate',
                'description' => 'Aggregate array items (count, sum, average, min, max).',
                'icon' => 'calculator',
                'color' => '#10B981',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['count', 'sum', 'average', 'min', 'max']],
                        'field' => ['type' => 'string', 'description' => 'Field to aggregate (not required for count)'],
                    ],
                    'required' => ['operation'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array'],
                    ],
                    'required' => ['items'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'result' => ['type' => 'number'],
                    ],
                ],
            ],
            [
                'category' => 'data',
                'type' => 'data.set_variable',
                'name' => 'Set Variable',
                'description' => 'Set a workflow-level variable for use in subsequent nodes.',
                'icon' => 'variable',
                'color' => '#10B981',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'variable_name' => ['type' => 'string'],
                        'value' => ['type' => 'string', 'description' => 'Static value or expression'],
                    ],
                    'required' => ['variable_name', 'value'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'variable_name' => ['type' => 'string'],
                        'value' => [],
                    ],
                ],
            ],
            [
                'category' => 'data',
                'type' => 'data.json_parse',
                'name' => 'JSON Parse',
                'description' => 'Parse a JSON string into a structured object.',
                'icon' => 'code-bracket',
                'color' => '#10B981',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'json_string' => ['type' => 'string'],
                    ],
                    'required' => ['json_string'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                    ],
                ],
            ],

            // ── Communication ─────────────────────────────────────────
            [
                'category' => 'communication',
                'type' => 'comm.send_email',
                'name' => 'Send Email',
                'description' => 'Send an email using SMTP or a transactional email provider.',
                'icon' => 'envelope',
                'color' => '#EC4899',
                'node_kind' => 'action',
                'credential_type' => 'smtp',
                'latency_hint_ms' => 1500,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'to' => ['type' => 'string'],
                        'subject' => ['type' => 'string'],
                        'body_type' => ['type' => 'string', 'enum' => ['text', 'html'], 'default' => 'html'],
                    ],
                    'required' => ['to', 'subject'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'body' => ['type' => 'string'],
                        'cc' => ['type' => 'string'],
                        'bcc' => ['type' => 'string'],
                    ],
                    'required' => ['body'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message_id' => ['type' => 'string'],
                        'sent' => ['type' => 'boolean'],
                    ],
                ],
            ],
            [
                'category' => 'communication',
                'type' => 'comm.slack_message',
                'name' => 'Slack',
                'description' => 'Send messages, list channels, or list users in Slack.',
                'icon' => 'chat-bubble-left',
                'color' => '#EC4899',
                'node_kind' => 'action',
                'credential_type' => 'slack',
                'latency_hint_ms' => 1000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['send_message', 'list_channels', 'list_users'], 'default' => 'send_message'],
                        'channel' => ['type' => 'string', 'description' => 'Channel ID or name (required for send_message)'],
                        'as_user' => ['type' => 'boolean', 'default' => false],
                    ],
                    'required' => ['operation'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
            [
                'category' => 'communication',
                'type' => 'comm.discord_message',
                'name' => 'Discord Message',
                'description' => 'Send a message to a Discord channel via webhook.',
                'icon' => 'chat-bubble-bottom-center-text',
                'color' => '#EC4899',
                'node_kind' => 'action',
                'credential_type' => 'discord',
                'latency_hint_ms' => 1000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'webhook_url' => ['type' => 'string'],
                        'username' => ['type' => 'string'],
                    ],
                    'required' => ['webhook_url'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string'],
                    ],
                    'required' => ['content'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                    ],
                ],
            ],

            // ── HTTP & APIs ───────────────────────────────────────────
            [
                'category' => 'http-apis',
                'type' => 'http.request',
                'name' => 'HTTP Request',
                'description' => 'Make an HTTP request to any URL.',
                'icon' => 'globe-alt',
                'color' => '#F97316',
                'node_kind' => 'action',
                'latency_hint_ms' => 2000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'method' => ['type' => 'string', 'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'], 'default' => 'GET'],
                        'url' => ['type' => 'string'],
                        'headers' => ['type' => 'object'],
                        'timeout' => ['type' => 'integer', 'default' => 30],
                        'response_type' => ['type' => 'string', 'enum' => ['json', 'text', 'binary'], 'default' => 'json'],
                    ],
                    'required' => ['method', 'url'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'body' => [],
                        'query_params' => ['type' => 'object'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'status_code' => ['type' => 'integer'],
                        'headers' => ['type' => 'object'],
                        'body' => [],
                    ],
                ],
            ],
            [
                'category' => 'http-apis',
                'type' => 'http.graphql',
                'name' => 'GraphQL Request',
                'description' => 'Execute a GraphQL query or mutation.',
                'icon' => 'code-bracket-square',
                'color' => '#F97316',
                'node_kind' => 'action',
                'latency_hint_ms' => 2000,
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'endpoint' => ['type' => 'string'],
                        'headers' => ['type' => 'object'],
                    ],
                    'required' => ['endpoint'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'variables' => ['type' => 'object'],
                    ],
                    'required' => ['query'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                        'errors' => ['type' => 'array'],
                    ],
                ],
            ],
            [
                'category' => 'http-apis',
                'type' => 'http.webhook_response',
                'name' => 'Webhook Response',
                'description' => 'Send a custom HTTP response back to the webhook caller.',
                'icon' => 'arrow-uturn-left',
                'color' => '#F97316',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'status_code' => ['type' => 'integer', 'default' => 200],
                        'headers' => ['type' => 'object'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'body' => [],
                    ],
                ],
            ],

            // ── Utility ───────────────────────────────────────────────
            [
                'category' => 'utility',
                'type' => 'util.code',
                'name' => 'Code (JavaScript)',
                'description' => 'Execute custom JavaScript code to transform data.',
                'icon' => 'code-bracket',
                'color' => '#6B7280',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string', 'description' => 'JavaScript code to execute'],
                    ],
                    'required' => ['code'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'object'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'result' => [],
                    ],
                ],
            ],
            [
                'category' => 'utility',
                'type' => 'util.template',
                'name' => 'Text Template',
                'description' => 'Render a text template with dynamic variables.',
                'icon' => 'document-text',
                'color' => '#6B7280',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'template' => ['type' => 'string', 'description' => 'Template with {{variable}} placeholders'],
                    ],
                    'required' => ['template'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'variables' => ['type' => 'object'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'category' => 'utility',
                'type' => 'util.logger',
                'name' => 'Logger',
                'description' => 'Log a message or data for debugging purposes.',
                'icon' => 'document-magnifying-glass',
                'color' => '#6B7280',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'level' => ['type' => 'string', 'enum' => ['debug', 'info', 'warning', 'error'], 'default' => 'info'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string'],
                        'data' => ['type' => 'object'],
                    ],
                    'required' => ['message'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'logged' => ['type' => 'boolean'],
                    ],
                ],
            ],
            [
                'category' => 'utility',
                'type' => 'util.error_handler',
                'name' => 'Error Handler',
                'description' => 'Catch and handle errors from upstream nodes.',
                'icon' => 'exclamation-triangle',
                'color' => '#6B7280',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'on_error' => ['type' => 'string', 'enum' => ['stop', 'continue', 'retry'], 'default' => 'stop'],
                        'max_retries' => ['type' => 'integer', 'default' => 3],
                        'retry_delay_seconds' => ['type' => 'integer', 'default' => 5],
                    ],
                ],
            ],

            // ── Storage ───────────────────────────────────────────────
            [
                'category' => 'storage',
                'type' => 'storage.read_file',
                'name' => 'Read File',
                'description' => 'Read the contents of a file from local or cloud storage.',
                'icon' => 'document-arrow-down',
                'color' => '#0EA5E9',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'disk' => ['type' => 'string', 'enum' => ['local', 's3', 'gcs'], 'default' => 'local'],
                        'path' => ['type' => 'string'],
                        'encoding' => ['type' => 'string', 'enum' => ['utf-8', 'base64'], 'default' => 'utf-8'],
                    ],
                    'required' => ['path'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string'],
                        'size' => ['type' => 'integer'],
                        'mime_type' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'category' => 'storage',
                'type' => 'storage.write_file',
                'name' => 'Write File',
                'description' => 'Write content to a file in local or cloud storage.',
                'icon' => 'document-arrow-up',
                'color' => '#0EA5E9',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'disk' => ['type' => 'string', 'enum' => ['local', 's3', 'gcs'], 'default' => 'local'],
                        'path' => ['type' => 'string'],
                    ],
                    'required' => ['path'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string'],
                    ],
                    'required' => ['content'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                        'size' => ['type' => 'integer'],
                    ],
                ],
            ],

            // ── Integrations (Apps) ───────────────────────────────────
            [
                'category' => 'data',
                'type' => 'notion.query_database',
                'name' => 'Notion',
                'description' => 'Create pages, query databases, or update content in Notion.',
                'icon' => 'document',
                'color' => '#000000',
                'node_kind' => 'action',
                'credential_type' => 'notion',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['query_database', 'create_page', 'update_page'], 'default' => 'query_database'],
                        'database_id' => ['type' => 'string'],
                        'page_id' => ['type' => 'string'],
                    ],
                    'required' => ['operation'],
                ],
            ],
            [
                'category' => 'data',
                'type' => 'google_sheets.get_rows',
                'name' => 'Google Sheets',
                'description' => 'Read, append, or update rows in Google Sheets via OAuth2.',
                'icon' => 'table-cells',
                'color' => '#10B981',
                'node_kind' => 'action',
                'credential_type' => 'google_oauth2',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['get_rows', 'append_row', 'update_row', 'clear_range', 'delete_rows', 'lookup_rows', 'create_spreadsheet', 'get_spreadsheet_info'], 'default' => 'get_rows'],
                        'spreadsheet_id' => ['type' => 'string', 'description' => 'ID of the spreadsheet'],
                        'range' => ['type' => 'string', 'default' => 'Sheet1'],
                    ],
                    'required' => ['operation', 'spreadsheet_id'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'values' => ['type' => 'array', 'description' => 'Array of arrays containing cell values'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'rows' => ['type' => 'array'],
                        'updated_rows' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'category' => 'storage',
                'type' => 'google_drive.list_files',
                'name' => 'Google Drive',
                'description' => 'List, create folders, or upload files to Google Drive.',
                'icon' => 'folder',
                'color' => '#0EA5E9',
                'node_kind' => 'action',
                'credential_type' => 'google_oauth2',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['list_files', 'download_file', 'upload_file', 'update_file', 'create_folder', 'delete_file', 'share_file'], 'default' => 'list_files'],
                        'folder_id' => ['type' => 'string', 'description' => 'Folder ID (optional)'],
                    ],
                    'required' => ['operation'],
                ],
            ],
            [
                'category' => 'communication',
                'type' => 'gmail.send_email',
                'name' => 'Gmail',
                'description' => 'Send emails, add labels, or list messages via Gmail.',
                'icon' => 'envelope',
                'color' => '#EC4899',
                'node_kind' => 'action',
                'credential_type' => 'google_oauth2',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['send_email', 'reply_to_message', 'get_message', 'modify_message', 'add_label', 'list_messages', 'list_labels', 'delete_message'], 'default' => 'send_email'],
                        'to' => ['type' => 'string', 'description' => 'Recipient email address'],
                        'subject' => ['type' => 'string', 'description' => 'Email subject'],
                        'body' => ['type' => 'string', 'description' => 'Email HTML body'],
                    ],
                    'required' => ['operation'],
                ],
            ],
            [
                'category' => 'utility',
                'type' => 'google_calendar.list_events',
                'name' => 'Google Calendar',
                'description' => 'List, create, update, or delete calendar events.',
                'icon' => 'calendar',
                'color' => '#6B7280',
                'node_kind' => 'action',
                'credential_type' => 'google_oauth2',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['list_events', 'get_event', 'create_event', 'update_event', 'delete_event', 'list_calendars'], 'default' => 'list_events'],
                        'calendar_id' => ['type' => 'string', 'default' => 'primary'],
                        'summary' => ['type' => 'string'],
                    ],
                    'required' => ['operation', 'calendar_id'],
                ],
            ],

            // ── Stripe ───────────────────────────────────────────────
            [
                'category' => 'data',
                'type' => 'stripe.create_customer',
                'name' => 'Stripe',
                'description' => 'Create customers, invoices, charges, and manage payments via Stripe.',
                'icon' => 'credit-card',
                'color' => '#635BFF',
                'node_kind' => 'action',
                'credential_type' => 'stripe',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['create_customer', 'create_invoice', 'list_payments', 'create_charge', 'get_balance'], 'default' => 'create_customer'],
                    ],
                    'required' => ['operation'],
                ],
            ],

            // ── Airtable ─────────────────────────────────────────────
            [
                'category' => 'data',
                'type' => 'airtable.list_records',
                'name' => 'Airtable',
                'description' => 'List, create, update, or delete records in Airtable bases.',
                'icon' => 'table-cells',
                'color' => '#18BFFF',
                'node_kind' => 'action',
                'credential_type' => 'airtable',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['list_records', 'get_record', 'create_record', 'update_record', 'delete_record'], 'default' => 'list_records'],
                        'base_id' => ['type' => 'string'],
                        'table_name' => ['type' => 'string'],
                        'record_id' => ['type' => 'string'],
                    ],
                    'required' => ['operation', 'base_id', 'table_name'],
                ],
            ],

            // ── GitHub ───────────────────────────────────────────────
            [
                'category' => 'data',
                'type' => 'github.list_repos',
                'name' => 'GitHub',
                'description' => 'List repos, create issues, manage pull requests on GitHub.',
                'icon' => 'code-bracket',
                'color' => '#24292E',
                'node_kind' => 'action',
                'credential_type' => 'github',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['list_repos', 'create_issue', 'list_issues', 'create_pull_request'], 'default' => 'list_repos'],
                        'owner' => ['type' => 'string'],
                        'repo' => ['type' => 'string'],
                    ],
                    'required' => ['operation'],
                ],
            ],

            // ── RAG (Retrieval-Augmented Generation) ─────────────────
            [
                'category' => 'ai',
                'type' => 'rag.document_loader',
                'name' => 'Document Loader',
                'description' => 'Load documents from various sources (text, URL, files) for RAG processing.',
                'icon' => 'document-text',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['load_text', 'load_url', 'load_file'],
                            'default' => 'load_text',
                            'description' => 'Source type to load documents from',
                        ],
                        'text' => ['type' => 'string', 'description' => 'Plain text content (for load_text)'],
                        'url' => ['type' => 'string', 'description' => 'URL to fetch content from (for load_url)'],
                        'file_path' => ['type' => 'string', 'description' => 'Path to file in storage (for load_file)'],
                        'document_id' => ['type' => 'string', 'description' => 'Unique identifier for the document'],
                        'metadata' => ['type' => 'object', 'description' => 'Additional metadata for the document'],
                    ],
                    'required' => ['operation'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'documents' => ['type' => 'array', 'description' => 'Array of loaded documents'],
                    ],
                ],
            ],
            [
                'category' => 'ai',
                'type' => 'rag.chunker',
                'name' => 'Text Chunker',
                'description' => 'Split documents into smaller chunks for embedding. Supports fixed-size and semantic chunking.',
                'icon' => 'scissors',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['chunk_fixed', 'chunk_semantic'],
                            'default' => 'chunk_fixed',
                            'description' => 'Chunking strategy',
                        ],
                        'chunk_size' => [
                            'type' => 'integer',
                            'default' => 1000,
                            'description' => 'Size of each chunk in characters (for chunk_fixed)',
                        ],
                        'overlap' => [
                            'type' => 'integer',
                            'default' => 200,
                            'description' => 'Overlap between chunks to preserve context (for chunk_fixed)',
                        ],
                        'max_chunk_size' => [
                            'type' => 'integer',
                            'default' => 1000,
                            'description' => 'Maximum chunk size (for chunk_semantic)',
                        ],
                    ],
                    'required' => ['operation'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'documents' => ['type' => 'array', 'description' => 'Array of documents to chunk'],
                    ],
                    'required' => ['documents'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'chunks' => ['type' => 'array', 'description' => 'Array of document chunks'],
                        'total_chunks' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'category' => 'ai',
                'type' => 'rag.vector_store_writer',
                'name' => 'Vector Store Writer',
                'description' => 'Store document chunks as embeddings in the vector database for retrieval.',
                'icon' => 'archive-box',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'credential_type' => 'openai',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['store', 'delete_document', 'delete_collection'],
                            'default' => 'store',
                        ],
                        'collection_name' => [
                            'type' => 'string',
                            'default' => 'default',
                            'description' => 'Name of the collection to store/retrieve from',
                        ],
                        'provider' => [
                            'type' => 'string',
                            'enum' => ['openai', 'anthropic', 'gemini'],
                            'default' => 'openai',
                            'description' => 'Embedding provider',
                        ],
                        'model' => [
                            'type' => 'string',
                            'default' => 'text-embedding-ada-002',
                            'description' => 'Embedding model',
                        ],
                        'document_id' => ['type' => 'string', 'description' => 'Document ID (for delete_document)'],
                    ],
                    'required' => ['operation', 'collection_name'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'chunks' => ['type' => 'array', 'description' => 'Array of chunks to store'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'collection_name' => ['type' => 'string'],
                        'chunks_stored' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'category' => 'ai',
                'type' => 'rag.query',
                'name' => 'RAG Query',
                'description' => 'Query your documents using Retrieval-Augmented Generation. Finds relevant context and generates AI-powered answers.',
                'icon' => 'magnifying-glass',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'credential_type' => 'openai',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['query'], 'default' => 'query'],
                        'query' => ['type' => 'string', 'description' => 'Question to ask about your documents'],
                        'collection_name' => [
                            'type' => 'string',
                            'default' => 'default',
                            'description' => 'Collection to search in',
                        ],
                        'top_k' => [
                            'type' => 'integer',
                            'default' => 5,
                            'description' => 'Number of similar documents to retrieve',
                        ],
                        'min_similarity' => [
                            'type' => 'number',
                            'default' => 0.7,
                            'minimum' => 0,
                            'maximum' => 1,
                            'description' => 'Minimum similarity score (0-1)',
                        ],
                        'provider' => [
                            'type' => 'string',
                            'enum' => ['openai', 'anthropic', 'gemini', 'groq'],
                            'default' => 'openai',
                        ],
                        'llm_model' => ['type' => 'string', 'default' => 'gpt-4o'],
                        'embedding_model' => ['type' => 'string', 'default' => 'text-embedding-ada-002'],
                        'system_prompt' => ['type' => 'string', 'description' => 'Custom system prompt for the LLM'],
                        'include_citations' => ['type' => 'boolean', 'default' => true],
                    ],
                    'required' => ['operation', 'query', 'collection_name'],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'answer' => ['type' => 'string', 'description' => 'AI-generated answer based on retrieved context'],
                        'sources' => ['type' => 'array', 'description' => 'Citations and source documents'],
                        'query' => ['type' => 'string'],
                        'retrieved_chunks' => ['type' => 'integer'],
                    ],
                ],
            ],

            // ── P0 Critical Nodes ───────────────────────────────────────
            
            // JSON Node
            [
                'category' => 'data',
                'type' => 'data.json',
                'name' => 'JSON',
                'description' => 'Parse, stringify, extract, merge, and validate JSON data.',
                'icon' => 'code-bracket',
                'color' => '#10B981',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['parse', 'stringify', 'extract', 'merge', 'validate'],
                            'default' => 'parse',
                        ],
                        'json_string' => ['type' => 'string', 'description' => 'JSON string to parse'],
                        'data' => ['type' => 'object', 'description' => 'Data to stringify'],
                        'path' => ['type' => 'string', 'description' => 'JSON path for extraction (dot notation)'],
                        'pretty_print' => ['type' => 'boolean', 'default' => false],
                    ],
                ],
            ],

            // Filter Node
            [
                'category' => 'data',
                'type' => 'data.filter',
                'name' => 'Filter',
                'description' => 'Filter arrays by conditions, values, remove duplicates, or empty items.',
                'icon' => 'funnel',
                'color' => '#F59E0B',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['filter_by_condition', 'filter_by_value', 'remove_duplicates', 'remove_empty'],
                            'default' => 'filter_by_condition',
                        ],
                        'field' => ['type' => 'string', 'description' => 'Field path to filter on'],
                        'operator' => [
                            'type' => 'string',
                            'enum' => ['equals', 'not_equals', 'contains', 'gt', 'lt', 'gte', 'lte', 'regex', 'in', 'is_empty', 'is_not_empty'],
                            'default' => 'equals',
                        ],
                        'value' => ['description' => 'Value to compare against'],
                        'mode' => ['type' => 'string', 'enum' => ['keep', 'remove'], 'default' => 'keep'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array', 'description' => 'Array to filter'],
                    ],
                    'required' => ['items'],
                ],
            ],

            // Array Node
            [
                'category' => 'data',
                'type' => 'data.array',
                'name' => 'Array Operations',
                'description' => 'Map, reduce, sort, group, flatten, and transform arrays.',
                'icon' => 'queue-list',
                'color' => '#8B5CF6',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['map', 'reduce', 'sort', 'group_by', 'unique', 'flatten', 'slice', 'chunk'],
                            'default' => 'map',
                        ],
                        'field' => ['type' => 'string', 'description' => 'Field to operate on'],
                        'fields' => ['type' => 'object', 'description' => 'Field mapping for map operation'],
                        'direction' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array'],
                    ],
                    'required' => ['items'],
                ],
            ],

            // String Node
            [
                'category' => 'data',
                'type' => 'data.string',
                'name' => 'String Operations',
                'description' => 'Concat, split, replace, regex, case conversion, trim, and template rendering.',
                'icon' => 'document-text',
                'color' => '#EC4899',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['concat', 'split', 'replace', 'regex', 'case', 'trim', 'substring', 'template', 'length'],
                            'default' => 'concat',
                        ],
                        'string' => ['type' => 'string', 'description' => 'Input string'],
                        'strings' => ['type' => 'array', 'description' => 'Strings to concatenate'],
                        'separator' => ['type' => 'string', 'description' => 'Separator for concat/split'],
                        'case' => ['type' => 'string', 'enum' => ['lower', 'upper', 'title', 'camel', 'snake', 'kebab']],
                    ],
                ],
            ],

            // Math Node
            [
                'category' => 'data',
                'type' => 'data.math',
                'name' => 'Math',
                'description' => 'Mathematical operations: calculate, aggregate, round, random, formulas.',
                'icon' => 'calculator',
                'color' => '#06B6D4',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['calculate', 'aggregate', 'round', 'random', 'formula'],
                            'default' => 'calculate',
                        ],
                        'a' => ['type' => 'number', 'description' => 'First number'],
                        'b' => ['type' => 'number', 'description' => 'Second number'],
                        'numbers' => ['type' => 'array', 'description' => 'Array of numbers for aggregation'],
                    ],
                ],
            ],

            // DateTime Node
            [
                'category' => 'data',
                'type' => 'data.datetime',
                'name' => 'Date/Time',
                'description' => 'Parse, format, add, subtract, compare dates and times. Timezone conversion.',
                'icon' => 'clock',
                'color' => '#F97316',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['parse', 'format', 'add', 'subtract', 'diff', 'compare', 'now', 'timezone'],
                            'default' => 'now',
                        ],
                        'date' => ['type' => 'string', 'description' => 'Date string or timestamp'],
                        'format' => ['type' => 'string', 'description' => 'Date format (Y-m-d H:i:s)'],
                        'timezone' => ['type' => 'string', 'description' => 'Timezone (UTC, America/New_York)'],
                        'unit' => ['type' => 'string', 'enum' => ['years', 'months', 'days', 'hours', 'minutes', 'seconds']],
                    ],
                ],
            ],

            // Try/Catch Node
            [
                'category' => 'flow-control',
                'type' => 'flow.try_catch',
                'name' => 'Try/Catch',
                'description' => 'Error handling node. Wrap nodes to catch and handle errors gracefully.',
                'icon' => 'shield-check',
                'color' => '#EF4444',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'catch_errors' => ['type' => 'boolean', 'default' => true],
                        'on_error' => ['type' => 'string', 'enum' => ['continue', 'stop', 'retry'], 'default' => 'continue'],
                        'retry_count' => ['type' => 'integer', 'default' => 0, 'description' => 'Number of retries'],
                        'retry_delay_ms' => ['type' => 'integer', 'default' => 1000, 'description' => 'Delay between retries (ms)'],
                        'log_errors' => ['type' => 'boolean', 'default' => true],
                        'fallback_value' => ['description' => 'Value to return on error'],
                    ],
                ],
            ],

            // Email Node
            [
                'category' => 'communication',
                'type' => 'communication.email',
                'name' => 'Email',
                'description' => 'Send single or bulk emails via SMTP. Supports HTML, attachments, CC, BCC.',
                'icon' => 'envelope',
                'color' => '#3B82F6',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['send', 'send_bulk'], 'default' => 'send'],
                        'to' => ['type' => 'string', 'description' => 'Recipient email'],
                        'recipients' => ['type' => 'array', 'description' => 'Array of recipients for bulk'],
                        'subject' => ['type' => 'string', 'description' => 'Email subject'],
                        'body' => ['type' => 'string', 'description' => 'Email body'],
                        'from' => ['type' => 'string', 'description' => 'Sender email'],
                        'from_name' => ['type' => 'string', 'description' => 'Sender name'],
                        'is_html' => ['type' => 'boolean', 'default' => true],
                        'cc' => ['type' => 'array', 'description' => 'CC recipients'],
                        'bcc' => ['type' => 'array', 'description' => 'BCC recipients'],
                        'delay_ms' => ['type' => 'integer', 'default' => 100, 'description' => 'Delay for bulk emails'],
                    ],
                    'required' => ['to', 'subject', 'body'],
                ],
            ],

            // ── Remaining P0 Nodes ───────────────────────────────────

            // Switch/Router Node
            [
                'category' => 'flow-control',
                'type' => 'flow.switch',
                'name' => 'Switch',
                'description' => 'Multi-way branching. Route to different paths based on value matching.',
                'icon' => 'arrows-pointing-out',
                'color' => '#A855F7',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'value' => ['description' => 'Value to match against cases'],
                        'cases' => ['type' => 'object', 'description' => 'Cases mapping (value => route)'],
                        'default_route' => ['type' => 'string', 'default' => 'default', 'description' => 'Default route if no match'],
                        'mode' => ['type' => 'string', 'enum' => ['exact', 'loose', 'regex', 'range', 'type', 'contains'], 'default' => 'exact'],
                    ],
                    'required' => ['value', 'cases'],
                ],
            ],

            // Wait for Event Node
            [
                'category' => 'flow-control',
                'type' => 'flow.wait_for_event',
                'name' => 'Wait for Event',
                'description' => 'Pause workflow until external event is received (webhook, signal, timeout).',
                'icon' => 'pause-circle',
                'color' => '#EC4899',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'event_type' => ['type' => 'string', 'enum' => ['webhook', 'signal', 'timeout'], 'default' => 'webhook'],
                        'event_id' => ['type' => 'string', 'description' => 'Unique event identifier'],
                        'timeout_seconds' => ['type' => 'integer', 'default' => 3600, 'description' => 'Timeout in seconds'],
                        'timeout_action' => ['type' => 'string', 'enum' => ['fail', 'continue', 'retry'], 'default' => 'fail'],
                    ],
                ],
            ],

            // Batch Processor Node
            [
                'category' => 'flow-control',
                'type' => 'flow.batch_processor',
                'name' => 'Batch Processor',
                'description' => 'Process items in configurable batches with commits and error handling.',
                'icon' => 'squares-2x2',
                'color' => '#14B8A6',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'batch_size' => ['type' => 'integer', 'default' => 100, 'minimum' => 1],
                        'pause_ms' => ['type' => 'integer', 'default' => 0, 'description' => 'Pause between batches (ms)'],
                        'commit_each_batch' => ['type' => 'boolean', 'default' => true],
                        'stop_on_error' => ['type' => 'boolean', 'default' => false],
                        'max_retries' => ['type' => 'integer', 'default' => 0],
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array'],
                    ],
                    'required' => ['items'],
                ],
            ],

            // Variable Node
            [
                'category' => 'data',
                'type' => 'data.variable',
                'name' => 'Variable',
                'description' => 'Store and retrieve variables across workflow executions. Supports workflow, execution, and workspace scopes.',
                'icon' => 'variable',
                'color' => '#F59E0B',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['set', 'get', 'increment', 'decrement', 'append', 'delete'], 'default' => 'set'],
                        'key' => ['type' => 'string', 'description' => 'Variable key'],
                        'value' => ['description' => 'Value to store'],
                        'scope' => ['type' => 'string', 'enum' => ['workflow', 'execution', 'workspace'], 'default' => 'workflow'],
                        'default' => ['description' => 'Default value for get operation'],
                        'ttl' => ['type' => 'integer', 'description' => 'Time to live in seconds'],
                    ],
                    'required' => ['operation', 'key'],
                ],
            ],

            // Cache Node
            [
                'category' => 'data',
                'type' => 'data.cache',
                'name' => 'Cache',
                'description' => 'Cache data for performance optimization. Get, set, has, delete, remember operations.',
                'icon' => 'server-stack',
                'color' => '#06B6D4',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['get', 'set', 'has', 'delete', 'clear', 'remember'], 'default' => 'get'],
                        'key' => ['type' => 'string', 'description' => 'Cache key'],
                        'value' => ['description' => 'Value to cache'],
                        'ttl' => ['type' => 'integer', 'description' => 'Time to live in seconds (null = forever)'],
                        'prefix' => ['type' => 'string', 'default' => 'workflow', 'description' => 'Cache key prefix'],
                        'default' => ['description' => 'Default value if key not found'],
                    ],
                    'required' => ['operation', 'key'],
                ],
            ],

            // Retry Node
            [
                'category' => 'flow-control',
                'type' => 'flow.retry',
                'name' => 'Retry',
                'description' => 'Retry failed operations with exponential backoff, linear, or fixed delay strategies.',
                'icon' => 'arrow-path',
                'color' => '#EF4444',
                'node_kind' => 'control',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'max_attempts' => ['type' => 'integer', 'default' => 3, 'minimum' => 1],
                        'initial_delay_ms' => ['type' => 'integer', 'default' => 1000],
                        'max_delay_ms' => ['type' => 'integer', 'default' => 60000],
                        'backoff_strategy' => ['type' => 'string', 'enum' => ['exponential', 'linear', 'fixed'], 'default' => 'exponential'],
                        'backoff_multiplier' => ['type' => 'number', 'default' => 2.0],
                        'jitter' => ['type' => 'boolean', 'default' => true],
                        'retry_on_errors' => ['type' => 'array', 'default' => ['all']],
                        'abort_on_errors' => ['type' => 'array', 'default' => []],
                    ],
                ],
            ],

            // Logger/Debug Node
            [
                'category' => 'debug',
                'type' => 'debug.logger',
                'name' => 'Logger',
                'description' => 'Log messages and debug workflow execution. Support for multiple log levels and channels.',
                'icon' => 'document-magnifying-glass',
                'color' => '#6366F1',
                'node_kind' => 'action',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['log', 'debug', 'inspect'], 'default' => 'log'],
                        'message' => ['type' => 'string', 'description' => 'Log message'],
                        'level' => ['type' => 'string', 'enum' => ['debug', 'info', 'warning', 'error'], 'default' => 'info'],
                        'data' => ['type' => 'object', 'description' => 'Additional data to log'],
                        'channel' => ['type' => 'string', 'default' => 'workflow', 'description' => 'Log channel'],
                        'include_context' => ['type' => 'boolean', 'default' => true],
                    ],
                ],
            ],
        ];

        foreach ($nodes as $nodeData) {
            $categorySlug = $nodeData['category'];
            unset($nodeData['category']);

            $nodeData['category_id'] = $categories[$categorySlug];
            $nodeData['is_active'] = ! in_array($nodeData['type'], self::DISABLED_NODES);
            $nodeData['is_premium'] ??= false;

            Node::query()->updateOrCreate(
                ['type' => $nodeData['type']],
                $nodeData,
            );
        }
    }
}
