# 💰 LLM API Cost Optimization Guide

**Advanced strategies to reduce AI/LLM costs by 80-95%**

---

## 📊 Cost Breakdown

### Current LLM API Pricing (December 2024)

| Provider | Model | Input (per 1M tokens) | Output (per 1M tokens) | Avg Cost/Call |
|----------|-------|----------------------|------------------------|---------------|
| **OpenAI** | GPT-4 Turbo | $10.00 | $30.00 | $0.06 |
| **OpenAI** | GPT-4o | $5.00 | $15.00 | $0.03 |
| **OpenAI** | GPT-3.5 Turbo | $0.50 | $1.50 | $0.003 |
| **Anthropic** | Claude Opus | $15.00 | $75.00 | $0.12 |
| **Anthropic** | Claude Sonnet | $3.00 | $15.00 | $0.03 |
| **Anthropic** | Claude Haiku | $0.25 | $1.25 | $0.002 |
| **Google** | Gemini Pro | $0.50 | $1.50 | $0.003 |
| **Google** | Gemini Flash | $0.075 | $0.30 | $0.0005 |

### Your Current Costs (Example Scenarios)

**Scenario 1: Small Business**
- 1,000 AI node executions/month
- Using GPT-4 Turbo
- **Monthly cost:** $60

**Scenario 2: Growing Startup**
- 10,000 AI node executions/month
- Using GPT-4 Turbo
- **Monthly cost:** $600

**Scenario 3: High-Volume SaaS**
- 100,000 AI node executions/month
- Using GPT-4 Turbo
- **Monthly cost:** $6,000

**Scenario 4: Enterprise**
- 1,000,000 AI node executions/month
- Using GPT-4 Turbo
- **Monthly cost:** $60,000 💸

---

## 🎯 Optimization Strategies

### Strategy 1: Semantic Caching (60-80% savings)

**Problem:** Identical or similar prompts make redundant API calls.

**Solution:** Cache responses by semantic similarity, not exact match.

#### Basic Implementation

```php
// app/Services/LLM/SemanticCache.php
namespace App\Services\LLM;

class SemanticCache
{
    private VectorDatabase $vectorDb;
    private float $similarityThreshold = 0.95; // 95% similar
    
    /**
     * Get cached response for similar prompt
     */
    public function get(string $prompt, string $model): ?string
    {
        // Generate embedding for prompt
        $embedding = $this->getEmbedding($prompt);
        
        // Search for similar prompts (vector similarity)
        $similar = $this->vectorDb->search($embedding, [
            'model' => $model,
            'threshold' => $this->similarityThreshold,
            'limit' => 1,
        ]);
        
        if (!empty($similar)) {
            $cached = $similar[0];
            
            Log::info('Semantic cache HIT', [
                'original_prompt' => $prompt,
                'cached_prompt' => $cached['prompt'],
                'similarity' => $cached['score'],
                'saved_cost' => $this->estimateSavedCost($prompt, $model),
            ]);
            
            Metrics::increment('llm.cache.semantic_hit', [
                'model' => $model,
            ]);
            
            return $cached['response'];
        }
        
        Metrics::increment('llm.cache.miss', ['model' => $model]);
        return null;
    }
    
    /**
     * Store prompt and response
     */
    public function set(string $prompt, string $response, string $model): void
    {
        $embedding = $this->getEmbedding($prompt);
        
        $this->vectorDb->insert([
            'prompt' => $prompt,
            'response' => $response,
            'model' => $model,
            'embedding' => $embedding,
            'cached_at' => now(),
            'access_count' => 0,
        ]);
    }
    
    /**
     * Generate embedding for prompt (lightweight)
     */
    private function getEmbedding(string $prompt): array
    {
        // Use cheap embedding model
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-3-small', // $0.02 per 1M tokens
            'input' => $prompt,
        ]);
        
        return $response['data'][0]['embedding'];
    }
}
```

#### Advanced: Hierarchical Caching

```php
// app/Services/LLM/HierarchicalCache.php
class HierarchicalCache
{
    /**
     * Multi-level cache: Exact → Semantic → Fresh API call
     */
    public function getCachedOrGenerate(
        string $prompt,
        string $model,
        array $config = []
    ): string {
        // Level 1: Exact match (Redis - instant)
        $exactKey = 'llm:exact:' . md5($prompt . $model);
        if ($cached = Cache::get($exactKey)) {
            Metrics::increment('llm.cache.exact_hit');
            return $cached;
        }
        
        // Level 2: Semantic similarity (pgvector - ~10ms)
        if ($cached = $this->semanticCache->get($prompt, $model)) {
            // Promote to exact cache
            Cache::put($exactKey, $cached, 86400); // 24 hours
            return $cached;
        }
        
        // Level 3: Fresh API call
        Metrics::increment('llm.api_call', ['model' => $model]);
        $response = $this->callLLM($prompt, $model, $config);
        
        // Store in both caches
        Cache::put($exactKey, $response, 86400);
        $this->semanticCache->set($prompt, $response, $model);
        
        return $response;
    }
}
```

**Savings:**
- Hit rate: 60-80% with semantic caching
- Cost: $0.02 per 1M tokens for embeddings (vs $10-30 for LLM calls)
- **ROI:** 300-1500x cheaper for cache hits

**Monitoring:**

```php
// Track cache effectiveness
DB::table('llm_cost_tracking')->insert([
    'date' => now(),
    'total_requests' => 1000,
    'cache_hits' => 650,
    'api_calls' => 350,
    'cost_with_cache' => 21.00, // $0.06 * 350
    'cost_without_cache' => 60.00, // $0.06 * 1000
    'savings' => 39.00,
    'savings_percent' => 65,
]);
```

---

### Strategy 2: Model Router (70-90% savings)

**Problem:** Using expensive models for simple tasks.

**Solution:** Route requests to cheapest model that can handle the task.

#### Smart Model Router

```php
// app/Services/LLM/ModelRouter.php
class ModelRouter
{
    private array $modelCapabilities = [
        'gpt-4-turbo' => [
            'cost_per_call' => 0.06,
            'capabilities' => ['complex_reasoning', 'code_generation', 'creative_writing'],
            'max_tokens' => 128000,
            'speed' => 'slow',
        ],
        'gpt-4o' => [
            'cost_per_call' => 0.03,
            'capabilities' => ['reasoning', 'code_review', 'analysis'],
            'max_tokens' => 128000,
            'speed' => 'medium',
        ],
        'gpt-3.5-turbo' => [
            'cost_per_call' => 0.003,
            'capabilities' => ['simple_tasks', 'classification', 'summarization'],
            'max_tokens' => 16000,
            'speed' => 'fast',
        ],
        'claude-haiku' => [
            'cost_per_call' => 0.002,
            'capabilities' => ['simple_tasks', 'extraction', 'basic_qa'],
            'max_tokens' => 100000,
            'speed' => 'very_fast',
        ],
    ];
    
    /**
     * Select optimal model based on task
     */
    public function selectModel(string $prompt, array $requirements = []): string
    {
        // Analyze prompt complexity
        $complexity = $this->analyzeComplexity($prompt);
        
        // Check requirements
        $requiredCapabilities = $requirements['capabilities'] ?? [];
        $maxBudget = $requirements['max_cost'] ?? 0.06;
        $prioritizeSpeed = $requirements['prioritize_speed'] ?? false;
        
        // Score models
        $scored = [];
        foreach ($this->modelCapabilities as $model => $info) {
            $score = 0;
            
            // Cost score (lower is better)
            $score += (1 - ($info['cost_per_call'] / 0.06)) * 40;
            
            // Capability match
            if (empty($requiredCapabilities)) {
                $score += $this->matchCapability($complexity, $info['capabilities']) * 40;
            } else {
                $match = count(array_intersect($requiredCapabilities, $info['capabilities']));
                $score += ($match / count($requiredCapabilities)) * 40;
            }
            
            // Speed bonus
            if ($prioritizeSpeed) {
                $speedScore = match($info['speed']) {
                    'very_fast' => 20,
                    'fast' => 15,
                    'medium' => 10,
                    'slow' => 5,
                };
                $score += $speedScore;
            }
            
            // Budget constraint
            if ($info['cost_per_call'] > $maxBudget) {
                $score = 0; // Disqualify
            }
            
            $scored[$model] = $score;
        }
        
        // Return highest scoring model
        arsort($scored);
        $selected = array_key_first($scored);
        
        Log::info('Model router selected', [
            'prompt_length' => strlen($prompt),
            'complexity' => $complexity,
            'selected_model' => $selected,
            'cost' => $this->modelCapabilities[$selected]['cost_per_call'],
            'alternatives' => $scored,
        ]);
        
        return $selected;
    }
    
    /**
     * Analyze prompt complexity
     */
    private function analyzeComplexity(string $prompt): string
    {
        $length = strlen($prompt);
        $wordCount = str_word_count($prompt);
        
        // Check for complex indicators
        $hasCode = preg_match('/```|function|class|def |import /i', $prompt);
        $hasMultiStep = preg_match('/first.*then.*finally|step 1.*step 2/i', $prompt);
        $hasReasoning = preg_match('/analyze|explain why|reasoning|logic/i', $prompt);
        
        if ($hasCode || $hasMultiStep || $hasReasoning || $length > 2000) {
            return 'complex';
        } elseif ($length > 500 || $wordCount > 100) {
            return 'medium';
        } else {
            return 'simple';
        }
    }
    
    private function matchCapability(string $complexity, array $capabilities): float
    {
        return match($complexity) {
            'complex' => in_array('complex_reasoning', $capabilities) ? 1.0 : 0.3,
            'medium' => in_array('reasoning', $capabilities) ? 1.0 : 0.5,
            'simple' => in_array('simple_tasks', $capabilities) ? 1.0 : 0.8,
        };
    }
}
```

#### Auto-Routing in LLM Node

```php
// app/Engine/Nodes/Apps/Ai/SmartLlmNode.php
class SmartLlmNode extends NodeExecutor
{
    public function execute(array $config, array $inputs): array
    {
        $prompt = $this->interpolate($config['prompt'], $inputs);
        
        // User can specify model or let router decide
        if (!isset($config['model']) || $config['model'] === 'auto') {
            $router = new ModelRouter();
            $model = $router->selectModel($prompt, [
                'max_cost' => $config['max_cost'] ?? 0.06,
                'prioritize_speed' => $config['prioritize_speed'] ?? false,
            ]);
            
            Log::info("Auto-selected model: {$model}");
        } else {
            $model = $config['model'];
        }
        
        // Execute with selected model
        return $this->callLLM($prompt, $model, $config);
    }
}
```

**Savings Example:**

```
Original (all GPT-4 Turbo):
- 1000 simple tasks × $0.06 = $60.00
- 500 medium tasks × $0.06 = $30.00
- 100 complex tasks × $0.06 = $6.00
Total: $96.00

With Smart Routing:
- 1000 simple tasks × $0.002 (Haiku) = $2.00
- 500 medium tasks × $0.003 (GPT-3.5) = $1.50
- 100 complex tasks × $0.06 (GPT-4) = $6.00
Total: $9.50

Savings: $86.50 (90%)
```

---

### Strategy 3: Prompt Compression (30-50% savings)

**Problem:** Verbose prompts increase token count and cost.

**Solution:** Compress prompts without losing meaning.

#### Prompt Compressor

```php
// app/Services/LLM/PromptCompressor.php
class PromptCompressor
{
    /**
     * Compress prompt to reduce token count
     */
    public function compress(string $prompt): array
    {
        $original = $prompt;
        $originalTokens = $this->estimateTokens($original);
        
        // 1. Remove excessive whitespace
        $prompt = preg_replace('/\s+/', ' ', $prompt);
        
        // 2. Remove redundant words
        $prompt = $this->removeRedundancy($prompt);
        
        // 3. Use abbreviations for common phrases
        $prompt = $this->applyAbbreviations($prompt);
        
        // 4. Remove filler words
        $fillers = ['please', 'kindly', 'I would like', 'Could you', 'very', 'really'];
        foreach ($fillers as $filler) {
            $prompt = str_ireplace($filler, '', $prompt);
        }
        
        // 5. Simplify instructions
        $prompt = $this->simplifyInstructions($prompt);
        
        $compressedTokens = $this->estimateTokens($prompt);
        $reduction = (1 - ($compressedTokens / $originalTokens)) * 100;
        
        Log::info('Prompt compressed', [
            'original_tokens' => $originalTokens,
            'compressed_tokens' => $compressedTokens,
            'reduction_percent' => round($reduction, 2),
        ]);
        
        return [
            'compressed' => $prompt,
            'original_tokens' => $originalTokens,
            'compressed_tokens' => $compressedTokens,
            'savings_percent' => $reduction,
        ];
    }
    
    private function removeRedundancy(string $prompt): string
    {
        // Remove repeated sentences
        $sentences = explode('.', $prompt);
        $unique = array_unique($sentences);
        return implode('.', $unique);
    }
    
    private function applyAbbreviations(string $prompt): string
    {
        $abbreviations = [
            'for example' => 'e.g.',
            'that is' => 'i.e.',
            'and so on' => 'etc.',
            'as soon as possible' => 'ASAP',
            'United States' => 'US',
        ];
        
        foreach ($abbreviations as $full => $abbr) {
            $prompt = str_ireplace($full, $abbr, $prompt);
        }
        
        return $prompt;
    }
    
    private function simplifyInstructions(string $prompt): string
    {
        // Replace verbose instructions with concise ones
        $simplifications = [
            'You are a helpful assistant that' => 'Assistant:',
            'I need you to help me with' => 'Task:',
            'Please analyze the following and provide' => 'Analyze and provide',
            'Based on the information provided, ' => '',
        ];
        
        foreach ($simplifications as $verbose => $concise) {
            $prompt = str_ireplace($verbose, $concise, $prompt);
        }
        
        return $prompt;
    }
    
    private function estimateTokens(string $text): int
    {
        // Rough estimation: 1 token ≈ 4 characters
        return (int) ceil(strlen($text) / 4);
    }
}
```

**Example:**

```php
// Before compression
$prompt = "
Hello! I would like you to please help me analyze the following text 
and provide a detailed summary of the main points. Please be very thorough 
and make sure to include all important details. The text is as follows:
" . $text;

// Tokens: ~500

// After compression
$prompt = "Analyze text and summarize main points:\n" . $text;

// Tokens: ~250 (50% reduction)
```

---

### Strategy 4: Response Streaming with Early Termination (20-40% savings)

**Problem:** Generating full response when only partial needed.

**Solution:** Stream response and stop when sufficient.

```php
// app/Services/LLM/StreamingLLM.php
class StreamingLLM
{
    /**
     * Stream response and stop early if criteria met
     */
    public function streamWithEarlyStop(
        string $prompt,
        string $model,
        callable $stopCondition
    ): string {
        $accumulated = '';
        $tokenCount = 0;
        
        OpenAI::chat()->createStreamed([
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'stream' => true,
        ], function($chunk) use (&$accumulated, &$tokenCount, $stopCondition) {
            $content = $chunk['choices'][0]['delta']['content'] ?? '';
            
            if ($content) {
                $accumulated .= $content;
                $tokenCount += $this->estimateTokens($content);
                
                // Check stop condition
                if ($stopCondition($accumulated, $tokenCount)) {
                    Log::info('Early termination', [
                        'tokens_generated' => $tokenCount,
                        'accumulated_length' => strlen($accumulated),
                    ]);
                    
                    // Stop generation
                    return false; // Stop streaming
                }
            }
        });
        
        return $accumulated;
    }
}
```

**Usage:**

```php
// Stop after getting answer (don't need full explanation)
$answer = $streaming->streamWithEarlyStop(
    "What is 2+2? Explain in detail.",
    'gpt-4-turbo',
    function($text, $tokens) {
        // Stop after we get the answer
        return preg_match('/answer is (\d+)/i', $text);
    }
);

// Saved ~50% of tokens by not generating full explanation
```

---

### Strategy 5: Batch Processing (50% savings)

**Problem:** Individual API calls have overhead.

**Solution:** Use OpenAI Batch API for 50% discount.

```php
// app/Services/LLM/BatchProcessor.php
class BatchProcessor
{
    /**
     * Process multiple prompts in batch (50% cheaper)
     */
    public function processBatch(array $prompts, string $model): array
    {
        // Create batch file
        $batchFile = [];
        foreach ($prompts as $id => $prompt) {
            $batchFile[] = [
                'custom_id' => $id,
                'method' => 'POST',
                'url' => '/v1/chat/completions',
                'body' => [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ],
            ];
        }
        
        // Upload batch
        $file = Storage::put('batches/batch-' . time() . '.jsonl', 
            implode("\n", array_map('json_encode', $batchFile))
        );
        
        $fileId = OpenAI::files()->upload([
            'file' => $file,
            'purpose' => 'batch',
        ]);
        
        // Create batch job
        $batch = OpenAI::batches()->create([
            'input_file_id' => $fileId['id'],
            'endpoint' => '/v1/chat/completions',
            'completion_window' => '24h',
        ]);
        
        Log::info('Batch created', [
            'batch_id' => $batch['id'],
            'prompt_count' => count($prompts),
            'estimated_cost' => count($prompts) * 0.03, // 50% of $0.06
            'savings' => count($prompts) * 0.03,
        ]);
        
        return [
            'batch_id' => $batch['id'],
            'status' => 'processing',
            'estimated_completion' => now()->addHours(2),
        ];
    }
    
    /**
     * Check batch status and retrieve results
     */
    public function getBatchResults(string $batchId): ?array
    {
        $batch = OpenAI::batches()->retrieve($batchId);
        
        if ($batch['status'] !== 'completed') {
            return null; // Still processing
        }
        
        // Download results
        $outputFileId = $batch['output_file_id'];
        $content = OpenAI::files()->download($outputFileId);
        
        // Parse results
        $results = [];
        foreach (explode("\n", $content) as $line) {
            $item = json_decode($line, true);
            $results[$item['custom_id']] = $item['response']['body']['choices'][0]['message']['content'];
        }
        
        return $results;
    }
}
```

**Best For:**
- Non-urgent background processing
- Bulk data enrichment
- Report generation
- Email campaigns
- Content moderation

**Savings:** 50% discount on all batch requests

---

### Strategy 6: Function Calling Optimization (40-60% savings)

**Problem:** Generating structured data with regular completions is expensive.

**Solution:** Use function calling for structured output.

```php
// Instead of asking LLM to format JSON (expensive)
$prompt = "Extract name, email, phone from: 'John Doe, john@example.com, 555-1234'. Return as JSON.";
$response = OpenAI::chat()->create([
    'model' => 'gpt-4-turbo',
    'messages' => [['role' => 'user', 'content' => $prompt]],
]);
// Cost: ~$0.06, Tokens: ~150

// Use function calling (cheaper, more reliable)
$response = OpenAI::chat()->create([
    'model' => 'gpt-3.5-turbo', // Can use cheaper model
    'messages' => [['role' => 'user', 'content' => "Extract: 'John Doe, john@example.com, 555-1234'"]],
    'functions' => [
        [
            'name' => 'extract_contact',
            'description' => 'Extract contact information',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                    'phone' => ['type' => 'string'],
                ],
                'required' => ['name', 'email', 'phone'],
            ],
        ],
    ],
    'function_call' => ['name' => 'extract_contact'],
]);
// Cost: ~$0.003, Tokens: ~50

// Savings: 95%!
```

---

### Strategy 7: Local Model Fallback (100% savings for dev)

**Problem:** Development and testing consume API credits.

**Solution:** Use local models (Ollama) for development.

```php
// app/Services/LLM/HybridLLM.php
class HybridLLM
{
    /**
     * Use local model in dev, cloud in production
     */
    public function complete(string $prompt, string $model): string
    {
        if (app()->environment('local', 'testing')) {
            return $this->completeLocal($prompt);
        }
        
        return $this->completeCloud($prompt, $model);
    }
    
    private function completeLocal(string $prompt): string
    {
        // Call Ollama (local)
        $response = Http::post('http://localhost:11434/api/generate', [
            'model' => 'llama2', // Free, runs locally
            'prompt' => $prompt,
            'stream' => false,
        ]);
        
        return $response->json()['response'];
    }
}
```

**Setup Ollama:**

```bash
# Install Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Pull models
ollama pull llama2       # 7B params, good for dev
ollama pull codellama    # Code generation
ollama pull mistral      # Strong reasoning

# Run
ollama serve
```

**Savings:** 100% in development (zero API costs)

---

## 📊 Comprehensive Cost Comparison

### Before Optimization

```
Scenario: 10,000 AI requests/month with GPT-4 Turbo

Breakdown:
- 6,000 simple tasks × $0.06 = $360
- 3,000 medium tasks × $0.06 = $180
- 1,000 complex tasks × $0.06 = $60

Total: $600/month
Annual: $7,200
```

### After Full Optimization

```
Same 10,000 requests with all strategies:

Semantic Caching (70% hit rate):
- 7,000 cached × $0.00002 (embedding) = $0.14
- 3,000 API calls remaining

Model Routing on 3,000 API calls:
- 1,800 simple → Haiku: 1,800 × $0.002 = $3.60
- 900 medium → GPT-3.5: 900 × $0.003 = $2.70
- 300 complex → GPT-4: 300 × $0.06 = $18.00

Prompt Compression (30% reduction):
- Reduces above costs by 30%: $24.30 × 0.7 = $17.01

Batch Processing (50% discount where applicable):
- 50% of simple/medium as batch: $3.15 × 0.5 = $1.58
- Running total: $17.01 - $1.58 = $15.43

Total: ~$15.50/month
Annual: $186

SAVINGS: $600 - $15.50 = $584.50/month (97.4% reduction!)
Annual savings: $7,014
```

---

## 🎯 Implementation Roadmap

### Week 1: Quick Wins (50-60% savings)
1. ✅ Implement basic caching (exact match)
2. ✅ Add model router for simple tasks
3. ✅ Use GPT-3.5-turbo instead of GPT-4 where possible

**Expected savings:** $300/month

### Week 2: Advanced (additional 20-30%)
1. ✅ Implement semantic caching
2. ✅ Add prompt compression
3. ✅ Set up cost tracking dashboard

**Expected savings:** Additional $180/month

### Week 3: Optimization (additional 10-15%)
1. ✅ Implement batch processing for background jobs
2. ✅ Add function calling optimization
3. ✅ Set up Ollama for development

**Expected savings:** Additional $90/month

**Total savings after 3 weeks:** $570/month (95%)

---

## 📈 Monitoring & Alerts

### Cost Tracking Dashboard

```php
// app/Services/LLM/CostTracker.php
class CostTracker
{
    public function track(string $model, int $inputTokens, int $outputTokens, bool $cached): void
    {
        $pricing = $this->getPricing($model);
        $cost = ($inputTokens / 1_000_000 * $pricing['input']) + 
                ($outputTokens / 1_000_000 * $pricing['output']);
        
        DB::table('llm_costs')->insert([
            'date' => now()->toDateString(),
            'hour' => now()->hour,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => $cost,
            'cached' => $cached,
            'saved_cost' => $cached ? $cost : 0,
            'created_at' => now(),
        ]);
        
        // Real-time alerts
        $todayCost = $this->getTodayCost();
        if ($todayCost > config('llm.daily_budget', 100)) {
            $this->alertHighCost($todayCost);
        }
    }
}
```

### Grafana Dashboard Queries

```
# Total cost today
SELECT SUM(cost) FROM llm_costs WHERE date = CURRENT_DATE

# Cache hit rate
SELECT 
    COUNT(*) FILTER (WHERE cached = true) * 100.0 / COUNT(*) as cache_hit_rate
FROM llm_costs 
WHERE date = CURRENT_DATE

# Cost by model
SELECT model, SUM(cost) as total_cost
FROM llm_costs
WHERE date = CURRENT_DATE
GROUP BY model
ORDER BY total_cost DESC

# Savings from caching
SELECT SUM(saved_cost) as total_savings
FROM llm_costs
WHERE date = CURRENT_DATE
```

---

## 🚨 Red Flags & Alerts

### Set up alerts for:

1. **Daily cost > $100**
```php
if ($dailyCost > 100) {
    Slack::send("⚠️ LLM costs exceeded $100 today: $$dailyCost");
}
```

2. **Cache hit rate < 40%**
```php
if ($cacheHitRate < 0.4) {
    Slack::send("⚠️ Low cache hit rate: {$cacheHitRate}%");
}
```

3. **Single request > $1**
```php
if ($requestCost > 1.0) {
    Log::warning('Expensive LLM request', [
        'cost' => $requestCost,
        'model' => $model,
        'input_tokens' => $inputTokens,
        'output_tokens' => $outputTokens,
    ]);
}
```

4. **Using expensive model for simple task**
```php
if ($complexity === 'simple' && $model === 'gpt-4-turbo') {
    Log::warning('Using expensive model for simple task');
}
```

---

## 🎓 Best Practices

### DO ✅
- Always check cache before API call
- Use cheapest model that can handle the task
- Compress prompts before sending
- Batch non-urgent requests
- Use function calling for structured output
- Monitor costs daily
- Set budget limits per workspace

### DON'T ❌
- Don't use GPT-4 for simple classification
- Don't generate long responses when short ones suffice
- Don't make API calls in tight loops
- Don't ignore cache hit rates
- Don't hardcode expensive models
- Don't skip prompt optimization

---

**With these optimizations, you can reduce LLM costs by 90-97% while maintaining quality!** 🎉

*Last Updated: December 2024*
