# 💰 Cost Analysis & Optimization Guide

**Understanding and reducing operational costs for LinkFlow**

---

## 🎯 Executive Summary

Running a workflow automation platform like LinkFlow involves several cost centers. Here are your **top cost drivers** ranked by impact:

| Cost Driver | Monthly Impact | Optimization Potential |
|-------------|----------------|------------------------|
| **1. LLM API Calls** | 🔴 **VERY HIGH** ($1,000 - $10,000+) | ⚠️ High (50-80% reduction possible) |
| **2. Database Storage** | 🟡 **MEDIUM** ($50 - $500) | ✅ High (60-90% reduction possible) |
| **3. Compute/Servers** | 🟡 **MEDIUM** ($50 - $300) | ⚠️ Medium (20-40% reduction) |
| **4. Data Transfer** | 🟢 **LOW** ($10 - $100) | ✅ Medium (30-50% reduction) |
| **5. Redis/Cache** | 🟢 **LOW** ($10 - $50) | ✅ Low (marginal gains) |
| **6. Monitoring/Logs** | 🟢 **LOW** ($0 - $50) | ✅ Medium (50% reduction) |

**Total Estimated Monthly Cost:** $1,120 - $11,000 (varies heavily with usage)

---

## 🔴 #1: LLM API Calls (BIGGEST COST)

### Why This Costs So Much

Your platform supports AI/LLM nodes:
- OpenAI GPT-4/GPT-5 API calls
- Anthropic Claude API calls  
- Google Gemini API calls
- RAG (Retrieval-Augmented Generation) with embeddings

**Cost Examples:**
```
GPT-4 Turbo:
- Input: $10 per 1M tokens
- Output: $30 per 1M tokens
- Average conversation: ~2,000 tokens = $0.06

GPT-5.2 (when released):
- Estimated 2-5x more expensive

Claude Sonnet:
- Input: $3 per 1M tokens
- Output: $15 per 1M tokens
- Average conversation: ~2,000 tokens = $0.03

Embeddings (for RAG):
- text-embedding-3-small: $0.02 per 1M tokens
- text-embedding-3-large: $0.13 per 1M tokens
```

**Monthly Cost Scenarios:**
- **Low usage** (1,000 AI nodes/month): $30 - $60
- **Medium usage** (10,000 AI nodes/month): $300 - $600
- **High usage** (100,000 AI nodes/month): $3,000 - $6,000
- **Very high usage** (1M AI nodes/month): $30,000 - $60,000

---

### 💡 Cost Optimization Strategies for LLM

#### 1. **Implement Aggressive Caching** ⭐ HIGHEST IMPACT

```php
// app/Services/LLMCacheService.php
class LLMCacheService
{
    public function getCachedOrGenerate(string $prompt, array $config): string
    {
        // Generate cache key from prompt + config
        $cacheKey = 'llm:' . md5($prompt . json_encode($config));
        
        // Check cache (7 day TTL)
        if ($cached = Cache::get($cacheKey)) {
            Log::info('LLM cache hit - saved API call', ['key' => $cacheKey]);
            return $cached;
        }
        
        // Call LLM API
        $response = $this->callLLM($prompt, $config);
        
        // Cache for 7 days
        Cache::put($cacheKey, $response, now()->addDays(7));
        
        return $response;
    }
}
```

**Savings:** 30-60% reduction for repeated prompts

---

#### 2. **Use Cheaper Models When Possible**

```php
// Workflow node config
$modelTiers = [
    'complex_reasoning' => 'gpt-4-turbo',      // $0.06 per call
    'simple_tasks' => 'gpt-3.5-turbo',         // $0.002 per call (30x cheaper!)
    'classification' => 'claude-haiku',         // $0.001 per call
    'embeddings' => 'text-embedding-3-small',  // 6.5x cheaper than large
];
```

**Auto-detection logic:**
```php
// Automatically downgrade to cheaper model for simple tasks
if (strlen($prompt) < 500 && !$requiresReasoning) {
    $model = 'gpt-3.5-turbo'; // 30x cheaper
} else {
    $model = 'gpt-4-turbo';
}
```

**Savings:** 70-90% for tasks that don't need advanced reasoning

---

#### 3. **Implement Token Limits**

```php
// Prevent expensive long responses
$config = [
    'model' => 'gpt-4-turbo',
    'max_tokens' => 500, // Limit output length
    'temperature' => 0.3, // Lower = more deterministic = easier to cache
];
```

**Per workspace limits:**
```php
// Database: workspaces.settings
{
    "ai_limits": {
        "max_tokens_per_request": 1000,
        "max_requests_per_day": 100,
        "allowed_models": ["gpt-3.5-turbo", "claude-haiku"]
    }
}
```

**Savings:** 40-70% by preventing unbounded generation

---

#### 4. **Implement Rate Limiting & Budgets**

```php
// app/Middleware/AIBudgetGuard.php
public function handle(Request $request, Closure $next)
{
    $workspace = $request->attributes->get('workspace');
    
    // Check daily AI budget
    $todaySpend = $this->getAISpendToday($workspace->id);
    $dailyLimit = $workspace->settings['ai_daily_budget'] ?? 10.00; // $10
    
    if ($todaySpend >= $dailyLimit) {
        return response()->json([
            'error' => 'Daily AI budget exceeded',
            'spent' => $todaySpend,
            'limit' => $dailyLimit,
        ], 402); // Payment Required
    }
    
    return $next($request);
}
```

**Savings:** Prevents runaway costs from bugs or abuse

---

#### 5. **Batch Processing**

```php
// Instead of 10 separate calls:
// Cost: 10 × $0.06 = $0.60

// Use batch API (OpenAI Batch API):
$batch = [
    ['prompt' => 'Task 1...'],
    ['prompt' => 'Task 2...'],
    // ... 10 prompts
];

$response = OpenAI::batch($batch);
// Cost: $0.30 (50% discount for batch)
```

**Savings:** 50% with batch APIs

---

#### 6. **Use Open Source Models for Development**

```php
// .env
AI_PROVIDER=local  // Use Ollama locally
// or
AI_PROVIDER=openai // Production

// Development: Free local models
// Production: Paid APIs
```

**Savings:** 100% in development (but 0 in production)

---

### 📊 LLM Cost Reduction Summary

| Strategy | Implementation Effort | Savings | Priority |
|----------|----------------------|---------|----------|
| Caching | Low | 30-60% | 🔥 **DO FIRST** |
| Cheaper models | Low | 70-90% | 🔥 **DO FIRST** |
| Token limits | Low | 40-70% | ⭐ High |
| Rate limiting | Medium | Prevents runaway | ⭐ High |
| Batching | Medium | 50% | 🟡 Medium |
| Auto model selection | High | 50-80% | 🟡 Medium |

**Total Potential Savings:** 60-85% with combined strategies

---

## 🟡 #2: Database Storage (MEDIUM COST)

### Why This Costs Money

Your LinkFlow database stores:
- **Executions**: Every workflow run (with full input/output data)
- **Execution nodes**: Individual node results
- **Logs**: Detailed execution logs
- **pgvector embeddings**: For RAG features (1536 dimensions per embedding)

**Storage Growth:**
```
Average execution size: 50 KB
- Input data: 5 KB
- Output data: 10 KB  
- Node results: 30 KB
- Checkpoint data: 5 KB

If you run 100,000 workflows/month:
= 100,000 × 50 KB = 5 GB/month
= 60 GB/year

Vector embeddings (if using RAG):
- 1 embedding = 1536 floats × 4 bytes = 6 KB
- 1M documents = 6 GB just for vectors
```

**Cost Examples (PostgreSQL):**
- **Self-hosted:** $0.10/GB/month (storage only)
- **Managed (AWS RDS):** $0.23/GB/month + $0.20/GB backup
- **Managed (Supabase):** Included up to 8GB, then $0.125/GB

**Monthly Cost Scenarios:**
- **Low usage** (10 GB): $1 - $10
- **Medium usage** (100 GB): $10 - $50
- **High usage** (1 TB): $100 - $500

---

### 💡 Cost Optimization Strategies for Database

#### 1. **Aggressive Data Retention Policy** ⭐ HIGHEST IMPACT

Implement in database migrations:

```php
// database/migrations/xxxx_add_retention_policy.php
Schema::table('executions', function (Blueprint $table) {
    // Add retention policy based on plan tier
});
```

**Automated cleanup job:**

```php
// app/Console/Commands/CleanupOldExecutions.php
class CleanupOldExecutions extends Command
{
    public function handle()
    {
        $retentionPolicies = [
            'free' => 3,       // 3 days
            'starter' => 7,    // 7 days
            'pro' => 30,       // 30 days
            'teams' => 90,     // 90 days
            'enterprise' => 365, // 1 year
        ];
        
        Workspace::chunk(100, function ($workspaces) use ($retentionPolicies) {
            foreach ($workspaces as $workspace) {
                $plan = $workspace->subscription->plan ?? 'free';
                $retentionDays = $retentionPolicies[$plan];
                
                // Delete old executions
                Execution::where('workspace_id', $workspace->id)
                    ->where('created_at', '<', now()->subDays($retentionDays))
                    ->delete();
                
                $this->info("Cleaned workspace {$workspace->id}: {$plan} plan");
            }
        });
    }
}
```

**Schedule:**
```php
// app/Console/Kernel.php
$schedule->command('cleanup:old-executions')->daily();
```

**Savings:** 60-90% storage reduction

---

#### 2. **Compress Large JSONB Fields**

```php
// app/Models/Execution.php
protected $casts = [
    'input_data' => 'array',
    'output_data' => 'array',
];

// Override mutator to compress
public function setOutputDataAttribute($value)
{
    // Only compress if > 10 KB
    $json = json_encode($value);
    
    if (strlen($json) > 10240) {
        // Compress with gzip
        $compressed = gzcompress($json, 9);
        
        $this->attributes['output_data'] = base64_encode($compressed);
        $this->attributes['output_data_compressed'] = true;
    } else {
        $this->attributes['output_data'] = $json;
        $this->attributes['output_data_compressed'] = false;
    }
}

public function getOutputDataAttribute($value)
{
    if ($this->attributes['output_data_compressed'] ?? false) {
        return json_decode(gzuncompress(base64_decode($value)), true);
    }
    
    return json_decode($value, true);
}
```

**Savings:** 50-80% for large payloads

---

#### 3. **Offload Logs to Cheap Storage**

```php
// Store detailed logs in S3, keep only summary in DB
class Execution extends Model
{
    public function storeDetailedLogs(array $logs)
    {
        // Upload to S3
        $path = "execution-logs/{$this->id}.json.gz";
        Storage::disk('s3')->put($path, gzcompress(json_encode($logs)));
        
        // Store only reference in DB
        $this->update([
            'logs_location' => $path,
            'logs_summary' => $this->summarizeLogs($logs), // Small summary
        ]);
    }
    
    public function getDetailedLogs()
    {
        if ($this->logs_location) {
            $compressed = Storage::disk('s3')->get($this->logs_location);
            return json_decode(gzuncompress($compressed), true);
        }
        
        return [];
    }
}
```

**Cost comparison:**
- PostgreSQL: $0.23/GB/month
- S3 Standard: $0.023/GB/month (10x cheaper)
- S3 Glacier: $0.004/GB/month (57x cheaper for old data)

**Savings:** 80-90% for log storage

---

#### 4. **Optimize pgvector Storage**

If using RAG features:

```php
// Use smaller embeddings
$embeddingModels = [
    'text-embedding-3-small' => 512,  // 512 dimensions (2 KB per doc)
    'text-embedding-3-large' => 1536, // 1536 dimensions (6 KB per doc)
];

// Use small for most use cases (3x smaller storage)
$model = 'text-embedding-3-small';
```

**Savings:** 67% storage for RAG data

---

### 📊 Database Cost Reduction Summary

| Strategy | Implementation | Savings | Priority |
|----------|---------------|---------|----------|
| Retention policy | Medium | 60-90% | 🔥 **DO FIRST** |
| JSONB compression | Medium | 50-80% | ⭐ High |
| Offload logs to S3 | High | 80-90% | ⭐ High |
| Smaller embeddings | Low | 67% | 🟡 Medium |
| Partition old data | High | 30-50% | 🟢 Low |

**Total Potential Savings:** 70-95% with combined strategies

---

## 🟡 #3: Compute/Server Costs

### Current Infrastructure Needs

Based on your deployment docs:

**Minimum:**
- 2 vCPUs, 4 GB RAM, 20 GB SSD
- Cost: $5-10/month (Hetzner VPS)

**Recommended:**
- 4 vCPUs, 8 GB RAM, 50 GB SSD
- Cost: $20-40/month (Hetzner)
- Cost: $50-100/month (AWS, Azure)

**High-volume:**
- Multiple servers + load balancer
- Cost: $200-500/month

---

### 💡 Optimization Strategies

#### 1. **Use Cheaper VPS Providers**

| Provider | 4 vCPU / 8 GB | Notes |
|----------|---------------|-------|
| Hetzner | €14/mo ($15) | ⭐ Best value |
| DigitalOcean | $48/mo | Good |
| AWS EC2 | $69/mo | Enterprise features |
| Azure | $73/mo | Microsoft ecosystem |

**Savings:** 50-70% by choosing Hetzner

---

#### 2. **Enable Opcache & Optimize PHP**

```ini
# /etc/php/8.3/fpm/php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # Don't check for file changes
opcache.jit=tracing
opcache.jit_buffer_size=256M
```

**Performance gain:** 2-3x faster PHP execution
**Server cost savings:** Can handle 2-3x more traffic on same server

---

#### 3. **Optimize Queue Workers**

```php
// config/horizon.php
'production' => [
    'supervisor-1' => [
        'maxProcesses' => 5,      // Don't over-provision
        'memory' => 256,          // Restart at 256MB
        'maxTime' => 0,
        'maxJobs' => 1000,        // Restart after 1000 jobs
        'balanceMaxShift' => 1,
        'balanceCooldown' => 3,
    ],
],
```

**Savings:** 30% less RAM usage

---

## 🟢 #4: Data Transfer Costs

### What Causes Data Transfer Costs

- API responses (especially large JSONB data)
- Webhook payloads
- File uploads/downloads
- Database backups

**Cost:** Typically $0.09 - $0.15 per GB transferred

---

### 💡 Optimization Strategies

#### 1. **Paginate Large Responses**

```php
// Don't return all executions at once
Execution::paginate(20); // Only 20 at a time
```

#### 2. **Use Compression**

```nginx
# Nginx config
gzip on;
gzip_types application/json text/plain text/css application/javascript;
gzip_min_length 1024;
```

#### 3. **CDN for Static Assets**

Use Cloudflare (free) for static files.

**Savings:** 40-60% data transfer costs

---

## 📊 Total Cost Optimization Summary

### Before Optimization (High Usage Scenario)
```
LLM API calls:         $6,000/month
Database:              $500/month
Compute:               $200/month
Data transfer:         $100/month
Other:                 $100/month
─────────────────────────────────
TOTAL:                 $6,900/month
```

### After Optimization
```
LLM API calls:         $900/month   (85% reduction via caching + cheaper models)
Database:              $50/month    (90% reduction via retention + compression)
Compute:               $120/month   (40% reduction via optimization)
Data transfer:         $50/month    (50% reduction via compression)
Other:                 $80/month    (20% reduction)
─────────────────────────────────
TOTAL:                 $1,200/month (83% total savings!)
```

---

## 🎯 Implementation Priority

### Phase 1: Quick Wins (Week 1) 🔥
1. ✅ Implement LLM caching
2. ✅ Use cheaper models for simple tasks
3. ✅ Add data retention policy
4. ✅ Enable Nginx gzip compression

**Expected savings:** 50-60% immediately

---

### Phase 2: Major Improvements (Week 2-3) ⭐
1. ✅ Implement token limits
2. ✅ Add AI budget guards
3. ✅ Compress JSONB fields
4. ✅ Offload logs to S3

**Expected savings:** Additional 20-30%

---

### Phase 3: Advanced (Month 2) 🟡
1. ✅ Auto model selection based on task
2. ✅ Batch API processing
3. ✅ Database partitioning
4. ✅ CDN setup

**Expected savings:** Additional 10-15%

---

## 📋 Monitoring & Alerts

### Track Costs in Real-Time

```php
// app/Services/CostTracker.php
class CostTracker
{
    public function trackLLMCall(string $model, int $tokens, float $cost)
    {
        DB::table('cost_tracking')->insert([
            'workspace_id' => auth()->user()->workspace_id,
            'service' => 'llm',
            'provider' => 'openai',
            'model' => $model,
            'tokens' => $tokens,
            'cost_usd' => $cost,
            'created_at' => now(),
        ]);
        
        // Alert if daily spend > threshold
        $todaySpend = $this->getTodaySpend();
        if ($todaySpend > 100) {
            $this->alertHighSpend($todaySpend);
        }
    }
}
```

### Cost Dashboard

Show users their spending:
```
Today:     $12.50 (↑ 15% vs yesterday)
This week: $67.30
This month: $245.00 / $500 budget (49%)

Top costs:
1. LLM API calls: $180 (73%)
2. Storage: $45 (18%)
3. Compute: $20 (9%)
```

---

## 🚨 Red Flags to Watch

1. **LLM costs growing > 20% week-over-week** 
   → Check for loops, bugs, or abuse

2. **Database size > 100 GB**
   → Enable aggressive retention

3. **Single workflow execution > $1**
   → Audit that workflow's LLM usage

4. **Daily spend suddenly 10x normal**
   → Possible bug or attack

---

## 💡 Pro Tips

1. **Start on Free Plan yourself** - Test the limits
2. **Use Staging Environment** - Test with cheap models
3. **Monitor Per-Workspace** - Identify heavy users
4. **Charge More for AI Nodes** - Your credit system already does this (10 credits vs 1)
5. **Offer "Bring Your Own API Key"** - Let power users use their own OpenAI keys

---

**Need help implementing these optimizations? Check:**
- [Developer Handbook](../core/04-developer-handbook.md)
- [Security Guide](./security.md)
- [Database Schema](../reference/database-schema.md)

---

*Last Updated: December 2024*
*Estimated savings: 60-85% with full implementation*
