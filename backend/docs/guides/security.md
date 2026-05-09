# 🔒 Security Guide

**Comprehensive security guide for LinkFlow**

---

## Overview

Security is critical for a workflow automation platform that handles sensitive data, API credentials, and executes automated tasks. This guide covers all security aspects of LinkFlow from authentication to data encryption.

**Threat Model:** Multi-tenant SaaS platform handling:
- User credentials and personal data
- Third-party API keys and OAuth tokens
- Workflow definitions (may contain business logic)
- Execution data and logs
- Webhook endpoints receiving external data

---

## Table of Contents

1. [Authentication & Authorization](#authentication--authorization)
2. [API Security](#api-security)
3. [Data Encryption](#data-encryption)
4. [Credential Management](#credential-management)
5. [Multi-Tenancy Security](#multi-tenancy-security)
6. [Webhook Security](#webhook-security)
7. [Input Validation](#input-validation)
8. [SQL Injection Prevention](#sql-injection-prevention)
9. [XSS Prevention](#xss-prevention)
10. [CSRF Protection](#csrf-protection)
11. [Rate Limiting](#rate-limiting)
12. [Security Headers](#security-headers)
13. [File Upload Security](#file-upload-security)
14. [Logging & Audit Trail](#logging--audit-trail)
15. [Secrets Management](#secrets-management)
16. [Infrastructure Security](#infrastructure-security)
17. [Compliance](#compliance)
18. [Security Checklist](#security-checklist)
19. [Incident Response](#incident-response)

---

## Authentication & Authorization

### Laravel Passport (OAuth2)

LinkFlow uses Laravel Passport for API authentication.

**Implementation:**

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        \Laravel\Passport\Http\Middleware\CheckClientCredentials::class,
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];
```

**Token Security:**

```php
// config/passport.php
return [
    // Tokens expire after 15 days
    'tokens_expire_in' => now()->addDays(15),
    
    // Refresh tokens expire after 30 days
    'refresh_tokens_expire_in' => now()->addDays(30),
    
    // Personal access tokens never expire (use with caution)
    'personal_access_tokens_expire_in' => null,
];
```

**Best Practices:**

1. **Use short-lived tokens** (15-30 days max)
2. **Implement refresh token rotation**
3. **Revoke tokens on logout:**

```php
// app/Http/Controllers/Auth/LogoutController.php
public function logout(Request $request)
{
    $request->user()->token()->revoke();
    return response()->json(['message' => 'Logged out successfully']);
}
```

4. **Store tokens securely on client:**
   - Use httpOnly cookies (preferred)
   - Or encrypted localStorage with XSS protection

---

### Password Security

**Hashing:**

Laravel uses bcrypt by default (cost factor 12):

```php
// config/hashing.php
return [
    'driver' => 'bcrypt',
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
    ],
];
```

**Password Requirements:**

Implemented in `app/Http/Requests/RegisterRequest.php`:

```php
public function rules()
{
    return [
        'password' => [
            'required',
            'string',
            'min:12',
            'confirmed',
            'regex:/[a-z]/',      // lowercase
            'regex:/[A-Z]/',      // uppercase
            'regex:/[0-9]/',      // number
            'regex:/[@$!%*#?&]/', // special char
        ],
    ];
}
```

**Password Reset Security:**

```php
// config/auth.php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60, // 1 hour
        'throttle' => 60, // 1 attempt per minute
    ],
],
```

**Prevent password reuse:**

```php
// app/Models/User.php
protected static function boot()
{
    parent::boot();
    
    static::updating(function ($user) {
        if ($user->isDirty('password')) {
            // Store last 5 password hashes
            $oldPasswords = json_decode($user->old_passwords ?? '[]');
            array_unshift($oldPasswords, $user->getOriginal('password'));
            $oldPasswords = array_slice($oldPasswords, 0, 5);
            $user->old_passwords = json_encode($oldPasswords);
        }
    });
}
```

---

### Role-Based Access Control (RBAC)

**Roles:**
- `owner` - Full workspace access
- `admin` - Manage workspace, can't delete workspace
- `member` - Create/edit workflows
- `viewer` - Read-only access

**Permission Check Middleware:**

```php
// app/Http/Middleware/CheckWorkspacePermission.php
class CheckWorkspacePermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $workspace = $request->attributes->get('workspace');
        $user = $request->user();
        
        $member = $workspace->members()
            ->where('user_id', $user->id)
            ->first();
        
        if (!$member || !$this->hasPermission($member->role, $permission)) {
            abort(403, 'Insufficient permissions');
        }
        
        return $next($request);
    }
    
    private function hasPermission(string $role, string $permission): bool
    {
        $permissions = [
            'owner' => ['*'],
            'admin' => ['workflows.*', 'executions.*', 'members.read', 'credentials.*'],
            'member' => ['workflows.*', 'executions.read'],
            'viewer' => ['workflows.read', 'executions.read'],
        ];
        
        $allowed = $permissions[$role] ?? [];
        
        if (in_array('*', $allowed)) {
            return true;
        }
        
        foreach ($allowed as $pattern) {
            if (fnmatch($pattern, $permission)) {
                return true;
            }
        }
        
        return false;
    }
}
```

**Usage in routes:**

```php
// routes/api.php
Route::middleware(['workspace', 'permission:workflows.delete'])->group(function () {
    Route::delete('/workflows/{workflow}', [WorkflowController::class, 'destroy']);
});
```

---

### Two-Factor Authentication (2FA)

**Implementation with Laravel Fortify:**

```bash
composer require laravel/fortify
php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
```

```php
// app/Models/User.php
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use TwoFactorAuthenticatable;
}
```

**Enforce 2FA for workspace:**

```php
// app/Http/Middleware/RequireTwoFactor.php
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $workspace = $request->attributes->get('workspace');
        $user = $request->user();
        
        if ($workspace->settings['require_2fa'] ?? false) {
            if (!$user->two_factor_secret) {
                return response()->json([
                    'error' => '2FA required',
                    'redirect' => '/settings/2fa',
                ], 403);
            }
        }
        
        return $next($request);
    }
}
```

---

## API Security

### HTTPS Only

**Enforce HTTPS in production:**

```php
// app/Providers/AppServiceProvider.php
public function boot()
{
    if (app()->environment('production')) {
        URL::forceScheme('https');
    }
}
```

**Redirect HTTP to HTTPS (Nginx):**

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}
```

---

### CORS Configuration

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    'allowed_origins' => [
        env('FRONTEND_URL', 'https://app.yourdomain.com'),
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

**⚠️ Never use:**
```php
'allowed_origins' => ['*'], // DO NOT USE IN PRODUCTION
```

---

### Rate Limiting

See [Rate Limiting](#rate-limiting) section.

---

## Data Encryption

### Encryption at Rest

**Sensitive Database Fields:**

Use Laravel's encrypted casting:

```php
// app/Models/Credential.php
class Credential extends Model
{
    protected $casts = [
        'data' => 'encrypted:array', // Encrypted JSON
        'api_key' => 'encrypted',     // Encrypted string
    ];
}
```

**How it works:**
```php
// Automatically encrypted when saved
$credential->api_key = 'sk-secret123';
$credential->save();
// Stored as: eyJpdiI6IkNxT...

// Automatically decrypted when retrieved
$key = $credential->api_key; // 'sk-secret123'
```

**Database-level encryption:**

For PostgreSQL, use pgcrypto:

```sql
CREATE EXTENSION pgcrypto;

-- Encrypt column
UPDATE credentials 
SET api_key = pgp_sym_encrypt(api_key, 'encryption-key');

-- Decrypt
SELECT pgp_sym_decrypt(api_key::bytea, 'encryption-key') FROM credentials;
```

---

### Encryption in Transit

**1. HTTPS for all API calls** (enforced)

**2. Database connections:**

```php
// config/database.php
'pgsql' => [
    // ...
    'sslmode' => 'require', // Force SSL
],
```

**3. Redis connections:**

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'options' => [
        'parameters' => [
            'password' => env('REDIS_PASSWORD'),
        ],
        'ssl' => [
            'verify_peer' => true,
        ],
    ],
],
```

---

### Key Management

**Application Key:**

Used for encryption/decryption:

```bash
# Generate strong key
php artisan key:generate

# Stored in .env
APP_KEY=base64:YOUR_KEY_HERE
```

**⚠️ Critical:**
- Never commit `APP_KEY` to git
- Rotate keys periodically (requires data re-encryption)
- Use different keys per environment

**Key Rotation:**

```bash
php artisan app:rotate-key --old-key=OLD_KEY
```

Custom command to re-encrypt data:

```php
// app/Console/Commands/RotateEncryptionKey.php
public function handle()
{
    $oldKey = $this->option('old-key');
    $newKey = config('app.key');
    
    // Re-encrypt credentials
    Credential::chunk(100, function ($credentials) use ($oldKey, $newKey) {
        foreach ($credentials as $credential) {
            $decrypted = decrypt($credential->getRawOriginal('api_key'), $oldKey);
            $credential->api_key = encrypt($decrypted, $newKey);
            $credential->save();
        }
    });
}
```

---

## Credential Management

### Third-Party API Credentials

LinkFlow stores user API keys for third-party integrations (OpenAI, Stripe, etc.).

**Security Measures:**

**1. Encrypted Storage:**

```php
// app/Models/Credential.php
class Credential extends Model
{
    protected $fillable = ['workspace_id', 'name', 'type', 'data'];
    
    protected $casts = [
        'data' => 'encrypted:array', // Encrypted JSON
    ];
    
    // Never expose raw credentials in API responses
    protected $hidden = ['data'];
}
```

**2. Masked Display:**

```php
// app/Http/Resources/CredentialResource.php
class CredentialResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'masked_key' => $this->maskCredential(),
            'created_at' => $this->created_at,
        ];
    }
    
    private function maskCredential()
    {
        $key = $this->data['api_key'] ?? '';
        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }
        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }
}
```

Output: `sk-1***************abc2`

**3. Workspace Isolation:**

```php
// Middleware ensures users can only access their workspace's credentials
Route::middleware('workspace')->get('/credentials', [CredentialController::class, 'index']);
```

**4. Credential Testing:**

Test credentials before saving:

```php
// app/Services/CredentialService.php
public function testCredential(array $data, string $type): bool
{
    return match($type) {
        'openai' => $this->testOpenAI($data['api_key']),
        'stripe' => $this->testStripe($data['api_key']),
        default => throw new \Exception('Unknown credential type'),
    };
}

private function testOpenAI(string $apiKey): bool
{
    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->get('https://api.openai.com/v1/models');
        
        return $response->successful();
    } catch (\Exception $e) {
        return false;
    }
}
```

---

## Multi-Tenancy Security

### Row-Level Security

All data is isolated by `workspace_id`.

**Global Scope:**

```php
// app/Models/Workflow.php
protected static function booted()
{
    static::addGlobalScope('workspace', function (Builder $query) {
        if (request()->attributes->has('workspace')) {
            $workspace = request()->attributes->get('workspace');
            $query->where('workspace_id', $workspace->id);
        }
    });
}
```

**Manual checks in controllers:**

```php
public function show(Request $request, Workflow $workflow)
{
    $workspace = $request->attributes->get('workspace');
    
    // Double-check workspace ownership
    if ($workflow->workspace_id !== $workspace->id) {
        abort(404); // Don't reveal existence
    }
    
    return new WorkflowResource($workflow);
}
```

**Database constraints:**

```sql
-- Ensure workspace_id is always set
ALTER TABLE workflows 
ALTER COLUMN workspace_id SET NOT NULL;

-- Index for performance
CREATE INDEX idx_workflows_workspace_id ON workflows(workspace_id);
```

---

### Preventing Cross-Tenant Data Leaks

**1. Never trust client input for workspace_id:**

```php
// ❌ BAD
$workflow = Workflow::create([
    'workspace_id' => $request->input('workspace_id'), // User can specify any workspace!
    'name' => $request->input('name'),
]);

// ✅ GOOD
$workspace = $request->attributes->get('workspace'); // From middleware
$workflow = $workflow->create([
    'workspace_id' => $workspace->id, // Always use authenticated workspace
    'name' => $request->input('name'),
]);
```

**2. Use UUIDs instead of auto-increment IDs:**

```php
// Prevents enumeration attacks
protected $keyType = 'string';
public $incrementing = false;

protected static function boot()
{
    parent::boot();
    static::creating(function ($model) {
        $model->id = (string) Str::uuid();
    });
}
```

**3. Audit logs for cross-tenant access attempts:**

```php
// Log failed authorization
if ($workflow->workspace_id !== $workspace->id) {
    Log::warning('Cross-tenant access attempt', [
        'user_id' => $request->user()->id,
        'workspace_id' => $workspace->id,
        'attempted_resource' => $workflow->id,
        'ip' => $request->ip(),
    ]);
    abort(404);
}
```

---

## Webhook Security

Webhooks receive external data and can trigger workflow executions.

### Signature Verification

**Generate webhook secret:**

```php
// When creating webhook
$webhook = Webhook::create([
    'workspace_id' => $workspace->id,
    'url' => '/webhooks/' . Str::random(32),
    'secret' => Str::random(64), // HMAC secret
]);
```

**Verify signature:**

```php
// app/Http/Controllers/WebhookController.php
public function handle(Request $request, string $webhookId)
{
    $webhook = Webhook::findOrFail($webhookId);
    
    // Verify signature
    $signature = $request->header('X-Webhook-Signature');
    $payload = $request->getContent();
    $expectedSignature = hash_hmac('sha256', $payload, $webhook->secret);
    
    if (!hash_equals($expectedSignature, $signature)) {
        Log::warning('Invalid webhook signature', [
            'webhook_id' => $webhookId,
            'ip' => $request->ip(),
        ]);
        abort(401, 'Invalid signature');
    }
    
    // Process webhook...
}
```

**Client sends:**

```bash
curl -X POST https://api.yourdomain.com/webhooks/abc123 \
  -H "X-Webhook-Signature: $(echo -n '{"event":"test"}' | openssl dgst -sha256 -hmac 'secret')" \
  -d '{"event":"test"}'
```

---

### Rate Limiting for Webhooks

```php
// app/Http/Middleware/ThrottleWebhooks.php
class ThrottleWebhooks
{
    public function handle(Request $request, Closure $next)
    {
        $webhookId = $request->route('webhookId');
        $key = 'webhook_throttle:' . $webhookId;
        
        // 100 requests per minute per webhook
        if (RateLimiter::tooManyAttempts($key, 100)) {
            abort(429, 'Too many requests');
        }
        
        RateLimiter::hit($key, 60);
        
        return $next($request);
    }
}
```

---

### IP Whitelisting

```php
// Workspace settings
$workspace->settings = [
    'webhook_ip_whitelist' => ['192.168.1.0/24', '10.0.0.1'],
];

// Middleware check
public function handle(Request $request, Closure $next)
{
    $workspace = $request->attributes->get('workspace');
    $whitelist = $workspace->settings['webhook_ip_whitelist'] ?? [];
    
    if (!empty($whitelist) && !$this->ipInWhitelist($request->ip(), $whitelist)) {
        Log::warning('Webhook from non-whitelisted IP', [
            'ip' => $request->ip(),
            'workspace_id' => $workspace->id,
        ]);
        abort(403, 'IP not whitelisted');
    }
    
    return $next($request);
}
```

---

## Input Validation

### Request Validation

**Always use Form Requests:**

```php
// app/Http/Requests/CreateWorkflowRequest.php
class CreateWorkflowRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Authorization handled by middleware
    }
    
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'trigger_type' => 'required|in:webhook,schedule,manual,polling',
            'trigger_config' => 'required|array',
            'nodes' => 'required|array',
            'nodes.*.id' => 'required|string',
            'nodes.*.type' => 'required|string|exists:nodes,type',
            'nodes.*.position' => 'required|array',
            'nodes.*.position.x' => 'required|numeric',
            'nodes.*.position.y' => 'required|numeric',
            'nodes.*.config' => 'nullable|array',
        ];
    }
    
    public function messages()
    {
        return [
            'name.required' => 'Workflow name is required',
            'trigger_type.in' => 'Invalid trigger type',
        ];
    }
}
```

**Usage:**

```php
public function store(CreateWorkflowRequest $request)
{
    // $request is already validated
    $workflow = Workflow::create($request->validated());
}
```

---

### Sanitize User Input

```php
use Illuminate\Support\Str;

// Remove HTML tags
$clean = strip_tags($input);

// Sanitize for HTML output (prevent XSS)
$safe = e($input); // Laravel helper
$safe = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

// Sanitize URLs
$url = filter_var($input, FILTER_SANITIZE_URL);
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    throw new \Exception('Invalid URL');
}

// Sanitize email
$email = filter_var($input, FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new \Exception('Invalid email');
}
```

---

## SQL Injection Prevention

Laravel's query builder protects against SQL injection by default.

**✅ Safe (parameterized):**

```php
// Eloquent
$users = User::where('email', $request->input('email'))->get();

// Query builder
$users = DB::table('users')->where('email', $email)->get();

// Raw with bindings
$users = DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

**❌ Unsafe (never do this):**

```php
// String concatenation - VULNERABLE TO SQL INJECTION
$users = DB::select("SELECT * FROM users WHERE email = '" . $email . "'");
```

**Dynamic table/column names:**

```php
// Validate against whitelist
$allowedTables = ['workflows', 'executions'];
$table = $request->input('table');

if (!in_array($table, $allowedTables)) {
    throw new \Exception('Invalid table');
}

$results = DB::table($table)->get();
```

---

## XSS Prevention

### Blade Templates (Auto-Escaped)

```blade
{{-- Automatically escaped --}}
<p>{{ $userInput }}</p>

{{-- Raw HTML (use only for trusted content) --}}
<div>{!! $trustedHtml !!}</div>
```

### JSON Responses

```php
// Laravel automatically escapes JSON
return response()->json([
    'message' => $userInput, // Safe
]);
```

### Content Security Policy (CSP)

```php
// app/Http/Middleware/SetSecurityHeaders.php
public function handle(Request $request, Closure $next)
{
    $response = $next($request);
    
    $response->headers->set('Content-Security-Policy', 
        "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: https:; "
        . "font-src 'self' data:; "
        . "connect-src 'self' https://api.openai.com;"
    );
    
    return $response;
}
```

---

## CSRF Protection

Laravel includes CSRF protection for all POST/PUT/DELETE requests.

**API Routes (Stateless):**

API routes typically don't use CSRF tokens (rely on token authentication instead).

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'api/*', // Exclude API routes
    'webhooks/*', // Exclude webhooks
];
```

**Web Routes (Stateful):**

CSRF token required:

```html
<form method="POST" action="/login">
    @csrf
    <input type="email" name="email">
    <button type="submit">Login</button>
</form>
```

---

## Rate Limiting

### API Rate Limiting

**Global rate limits:**

```php
// app/Providers/RouteServiceProvider.php
protected function configureRateLimiting()
{
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });
    
    RateLimiter::for('auth', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip()); // Strict for auth endpoints
    });
    
    RateLimiter::for('webhooks', function (Request $request) {
        return Limit::perMinute(100)->by($request->route('webhookId'));
    });
}
```

**Apply to routes:**

```php
// routes/api.php
Route::middleware('throttle:api')->group(function () {
    Route::get('/workflows', [WorkflowController::class, 'index']);
});

Route::middleware('throttle:auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});
```

---

### Custom Rate Limiting

**Per-user limits:**

```php
RateLimiter::for('workflow-execution', function (Request $request) {
    $user = $request->user();
    
    // Free tier: 100 executions/day
    // Pro tier: 10,000 executions/day
    $limit = match($user->subscription_tier) {
        'pro' => 10000,
        'enterprise' => 100000,
        default => 100,
    };
    
    return Limit::perDay($limit)->by($user->id);
});
```

**Respond to rate limit exceeded:**

```php
public function executeWorkflow(Request $request)
{
    $key = 'workflow-execution:' . $request->user()->id;
    
    if (RateLimiter::tooManyAttempts($key, 100)) {
        $seconds = RateLimiter::availableIn($key);
        
        return response()->json([
            'error' => 'Rate limit exceeded',
            'retry_after' => $seconds,
        ], 429);
    }
    
    RateLimiter::hit($key, 86400); // 24 hours
    
    // Execute workflow...
}
```

---

## Security Headers

### Middleware Implementation

```php
// app/Http/Middleware/SetSecurityHeaders.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        
        // Prevent MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // XSS protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Force HTTPS
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        
        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions policy
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        // Content Security Policy
        $response->headers->set('Content-Security-Policy', $this->getCsp());
        
        return $response;
    }
    
    private function getCsp(): string
    {
        return "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: https:; "
            . "font-src 'self' data:; "
            . "connect-src 'self' https://api.openai.com; "
            . "frame-ancestors 'none'; "
            . "base-uri 'self'; "
            . "form-action 'self';";
    }
}
```

**Register middleware:**

```php
// app/Http/Kernel.php
protected $middleware = [
    // ...
    \App\Http\Middleware\SetSecurityHeaders::class,
];
```

---

## File Upload Security

### Validation

```php
public function upload(Request $request)
{
    $request->validate([
        'file' => [
            'required',
            'file',
            'max:10240', // 10MB
            'mimes:jpg,jpeg,png,pdf,csv', // Whitelist
        ],
    ]);
    
    $file = $request->file('file');
    
    // Validate MIME type (don't trust extension)
    $mimeType = $file->getMimeType();
    $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf', 'text/csv'];
    
    if (!in_array($mimeType, $allowedMimes)) {
        abort(422, 'Invalid file type');
    }
    
    // Generate secure filename
    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
    
    // Store in private storage (not publicly accessible)
    $path = $file->storeAs('uploads', $filename, 'private');
    
    return response()->json(['path' => $path]);
}
```

### Prevent Malicious Files

```php
// Scan uploaded files (ClamAV integration)
use Xenolope\Quahog\Client;

public function scanFile(string $path): bool
{
    $quahog = new Client('unix:///var/run/clamav/clamd.sock');
    $result = $quahog->scanFile($path);
    
    return $result['status'] === 'OK';
}
```

---

## Logging & Audit Trail

### Security Event Logging

```php
// app/Services/AuditLogger.php
class AuditLogger
{
    public static function log(string $event, array $data = [])
    {
        DB::table('audit_logs')->insert([
            'event' => $event,
            'user_id' => auth()->id(),
            'workspace_id' => request()->attributes->get('workspace')?->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data' => json_encode($data),
            'created_at' => now(),
        ]);
    }
}
```

**Log critical events:**

```php
// Login
AuditLogger::log('user.login', ['email' => $user->email]);

// Failed login
AuditLogger::log('user.login.failed', ['email' => $request->email]);

// Workflow deleted
AuditLogger::log('workflow.deleted', ['workflow_id' => $workflow->id]);

// Credential created
AuditLogger::log('credential.created', ['credential_id' => $credential->id, 'type' => $credential->type]);

// Permission denied
AuditLogger::log('authorization.denied', ['action' => 'workflows.delete', 'resource_id' => $workflow->id]);
```

---

## Secrets Management

### Environment Variables

**Never commit secrets to git:**

```bash
# .gitignore
.env
.env.backup
.env.production
```

**Use `.env` files:**

```env
APP_KEY=base64:...
DB_PASSWORD=...
REDIS_PASSWORD=...
AWS_SECRET_ACCESS_KEY=...
```

---

### External Secrets Management

**For production, use:**

**1. AWS Secrets Manager:**

```php
use Aws\SecretsManager\SecretsManagerClient;

$client = new SecretsManagerClient(['region' => 'us-east-1']);
$result = $client->getSecretValue(['SecretId' => 'prod/linkflow/db']);
$secret = json_decode($result['SecretString'], true);

config(['database.connections.pgsql.password' => $secret['password']]);
```

**2. HashiCorp Vault:**

```php
use Vault\Client;

$client = new Client('http://127.0.0.1:8200');
$client->setToken(env('VAULT_TOKEN'));
$response = $client->read('secret/data/linkflow');

config(['database.connections.pgsql.password' => $response['data']['password']]);
```

**3. Kubernetes Secrets:**

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: linkflow-secrets
type: Opaque
data:
  db-password: base64-encoded-password
```

---

## Infrastructure Security

### Firewall Configuration (UFW)

```bash
# Default deny
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Allow SSH (change port from 22)
sudo ufw allow 2222/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable
```

---

### SSH Hardening

**Edit `/etc/ssh/sshd_config`:**

```
# Change default port
Port 2222

# Disable root login
PermitRootLogin no

# Use key-based authentication only
PasswordAuthentication no
PubkeyAuthentication yes

# Disable empty passwords
PermitEmptyPasswords no

# Limit login attempts
MaxAuthTries 3

# Disconnect idle sessions
ClientAliveInterval 300
ClientAliveCountMax 2
```

Restart SSH:
```bash
sudo systemctl restart sshd
```

---

### Database Security

**PostgreSQL hardening:**

```sql
-- Limit connections
ALTER SYSTEM SET max_connections = 100;

-- Require SSL
ALTER SYSTEM SET ssl = on;

-- Log connections
ALTER SYSTEM SET log_connections = on;
ALTER SYSTEM SET log_disconnections = on;

-- Log slow queries
ALTER SYSTEM SET log_min_duration_statement = 1000; -- 1 second
```

**Edit `/etc/postgresql/17/main/pg_hba.conf`:**

```
# Require SSL for all connections
hostssl all all 0.0.0.0/0 md5

# Or limit to specific IPs
hostssl linkflow_prod linkflow_user 10.0.0.0/8 md5
```

---

## Compliance

### GDPR Compliance

**1. Right to Access:**

```php
public function exportUserData(User $user)
{
    return response()->json([
        'user' => $user,
        'workspaces' => $user->workspaces,
        'workflows' => Workflow::whereHas('workspace.members', fn($q) => $q->where('user_id', $user->id))->get(),
        'executions' => Execution::whereHas('workflow.workspace.members', fn($q) => $q->where('user_id', $user->id))->get(),
    ]);
}
```

**2. Right to Erasure (Right to be Forgotten):**

```php
public function deleteUserData(User $user)
{
    DB::transaction(function () use ($user) {
        // Anonymize audit logs
        DB::table('audit_logs')->where('user_id', $user->id)->update([
            'user_id' => null,
            'data' => json_encode(['anonymized' => true]),
        ]);
        
        // Delete user's workspaces (if owner)
        $user->ownedWorkspaces()->each(fn($ws) => $ws->delete());
        
        // Remove from other workspaces
        $user->workspaces()->detach();
        
        // Delete user
        $user->delete();
    });
}
```

**3. Data Retention Policy:**

```php
// Delete executions older than 90 days
Execution::where('created_at', '<', now()->subDays(90))->delete();

// Delete failed executions after 30 days
Execution::where('status', 'failed')
    ->where('created_at', '<', now()->subDays(30))
    ->delete();
```

---

### SOC 2 Compliance

**Key requirements:**

1. **Access Controls:** RBAC implemented ✅
2. **Encryption:** At rest and in transit ✅
3. **Audit Logging:** All security events logged ✅
4. **Incident Response:** See [Incident Response](#incident-response)
5. **Vendor Management:** Document all third-party integrations
6. **Change Management:** Use CI/CD with approval process
7. **Monitoring:** See [Monitoring Guide](./deployment/08-monitoring.md)

---

## Security Checklist

### Development

```
□ Use parameterized queries (prevent SQL injection)
□ Validate all user input
□ Escape output (prevent XSS)
□ Use CSRF protection for web routes
□ Use Form Requests for validation
□ Never commit secrets to git
□ Use encrypted casts for sensitive data
□ Implement proper error handling (don't expose stack traces in production)
□ Use UUIDs instead of auto-increment IDs
□ Implement rate limiting
```

### Deployment

```
□ APP_DEBUG=false in production
□ Strong APP_KEY generated
□ HTTPS enforced
□ Security headers configured
□ Firewall configured (UFW)
□ SSH hardened (key-only, non-standard port)
□ Database SSL required
□ Regular backups configured
□ Monitoring and alerting set up
□ Log rotation configured
```

### Ongoing

```
□ Regular security audits
□ Dependency updates (composer update, npm update)
□ Review audit logs weekly
□ Rotate secrets quarterly
□ Penetration testing annually
□ Security training for developers
□ Incident response plan documented
□ Data retention policy enforced
```

---

## Incident Response

### Response Plan

**1. Detection:**
- Monitor error rates
- Set up alerts for failed logins
- Monitor audit logs for anomalies

**2. Containment:**
```bash
# Immediately revoke compromised tokens
php artisan passport:purge --revoked --expired

# Block malicious IPs at firewall
sudo ufw deny from 1.2.3.4

# Disable compromised user account
php artisan tinker
> User::where('email', 'compromised@example.com')->update(['is_active' => false]);
```

**3. Investigation:**
```sql
-- Check audit logs
SELECT * FROM audit_logs 
WHERE user_id = 'suspicious-user-id' 
ORDER BY created_at DESC;

-- Check recent logins
SELECT * FROM audit_logs 
WHERE event = 'user.login' 
AND created_at > NOW() - INTERVAL '24 hours';
```

**4. Recovery:**
- Restore from backups if needed
- Rotate all secrets
- Force password reset for affected users

**5. Post-Incident:**
- Document incident
- Update security measures
- Notify affected users (GDPR requirement if data breach)

---

### Emergency Contacts

```
Security Team: security@yourdomain.com
On-Call Engineer: +1-XXX-XXX-XXXX
Hosting Provider: support@hetzner.com
```

---

**Security is an ongoing process. Regularly review and update these measures!** 🔒

*Last Updated: December 2024*
