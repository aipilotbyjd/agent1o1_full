# Webhook Architecture Guide

This document explains the complete webhook system — how it works, why each piece
exists, and how to manage it day-to-day as the codebase grows.

---

## Big Picture

The webhook system has two distinct jobs:

1. **Receiving** — accepting incoming HTTP requests from external services (GitHub, Stripe, Slack, Discord) and turning them into workflow executions.
2. **Registration** — automatically telling those external services "send your events to our URL" when a workflow is activated, and "stop sending" when it's deactivated.

These two jobs are completely separate. Receiving is always-on and must be fast. Registration happens once per workflow activation and can be slow.

---

## How Receiving Works

```
External Service (GitHub, Stripe, Slack, Discord)
        │
        │  HTTP POST  /api/v1/webhook/{uuid}
        ▼
WebhookReceiverController::handle()
        │
        ├─ [1] Synchronous handshake? ────────────────────────────────────────┐
        │       Slack sends url_verification challenge?  → return challenge    │
        │       Discord sends PING (type=1)?             → return {"type":1}   │
        │       (Must be synchronous — provider rejects slow responses)        │
        │                                                                      │
        ├─ [2] Verify provider signature (HMAC / Ed25519)                     │
        │       Invalid?  → return 401 immediately                             │
        │       (Blocks fake/malicious requests before they touch the queue)   │
        │                                                                      │
        ├─ [3] response_mode = 'wait'?                                         │
        │       YES → WebhookService::handleIncoming (synchronous)             │
        │             caller receives workflow result in same HTTP response     │
        │                                                                      │
        └─ [4] response_mode = 'immediate' (default)
                → WebhookProcessingJob::dispatch() (async, high-priority queue)
                → return HTTP 200 in < 50ms
                        │
                        ▼ (on queue worker)
                WebhookProcessingJob::handle()
                  → load workflow
                  → ExecutionService::trigger()
                  → WorkflowEngine starts
```

### Why does the controller return 200 before execution finishes?

External services (GitHub, Stripe, Slack) all have a timeout: if they don't
receive HTTP 200 within ~10 seconds, they **retry the request**. A retry means
your workflow runs twice for one event.

By dispatching to a queue and returning 200 immediately, we ensure:
- The provider never retries (we always respond fast)
- No duplicate workflow executions
- The server can handle many more concurrent webhooks (workers are free in <50ms)

### When to use 'wait' mode

Use `response_mode = 'wait'` only when the caller **needs the workflow output
in the same HTTP connection** — for example, a payment form that shows the
customer what happened based on the workflow result.

Wait mode uses exponential backoff polling (100ms → 200ms → 400ms → 800ms → 3s)
and returns the execution in progress if it times out. The caller should check
`execution_id` in the response and poll `GET /api/v1/executions/{id}` if needed.

---

## How Registration Works

When a user activates a workflow, we need to tell external services to send
events to our URL. This is called **registration**.

```
User clicks "Activate Workflow"
        │
Workflow::activate()
        │
        ├─ workflow.is_active = true
        ├─ workflow.webhook_status = 'pending'
        └─ dispatch WebhookRegistrationJob
                │
                ▼ (on queue worker)
        WebhookRegistrationJob::handle()
                │
        WebhookAutoRegistrationService::registerForWorkflow()
                │
                ├─ extractTriggerNodes() — scan workflow JSON for trigger nodes
                │
                ├─ For each trigger node with a supported provider:
                │       │
                │       ├─ supportsAutoRegistration()? NO → skip (Discord: manual)
                │       │
                │       ├─ resolveCredentials() — get the user's API token
                │       │
                │       ├─ checkExists() — is webhook already on the provider?
                │       │       YES + URL unchanged → skip (prevent duplicate)
                │       │       YES + URL changed   → unregister old, re-register
                │       │       NO                  → register
                │       │
                │       └─ registrar.register(callbackUrl, events, credentials)
                │               → call GitHub/Stripe/Slack API
                │               → store external_id + secret + registered_url in DB
                │
                └─ workflow.webhook_status = 'active' (or 'failed')
```

When the workflow is **deactivated**:

```
User clicks "Deactivate"
        │
Workflow::deactivate()
        │
        ├─ workflow.is_active = false
        ├─ workflow.webhook_status = 'deregistering'
        └─ dispatch WebhookUnregistrationJob
                │
                ▼ (on queue worker)
        WebhookUnregistrationJob::handle()
                │
        WebhookAutoRegistrationService::unregisterForWorkflow()
                │
                └─ registrar.unregister(external_webhook_id, credentials)
                        → call GitHub/Stripe DELETE API
                        → clear external_webhook_id from DB
                        → workflow.webhook_status = null
```

### Why is registration async?

Calling GitHub's API inline (inside the user's activate request) means:
- If GitHub is slow (500ms response), the user's button click hangs
- If GitHub is down, the activation fails entirely
- Under load, you have many PHP workers waiting on GitHub

By dispatching a job, the user sees "active" immediately and registration
happens in the background. If it fails (bad credentials, GitHub API error),
`webhook_status = 'failed'` with a message the user can act on.

---

## The Registrar System

Every supported provider has a **registrar** — a class that knows how to talk
to that specific provider's API.

### Interface

```php
interface WebhookRegistrar
{
    public function provider(): string;             // 'github', 'stripe', etc.
    public function supportsAutoRegistration(): bool; // false for Discord
    public function checkExists(...): bool;         // does the hook still exist?
    public function register(...): array;           // create the hook, return id+secret
    public function unregister(...): void;          // delete the hook
    public function verifySignature(...): bool;     // verify incoming request is genuine
}
```

### Current Providers

| Provider | Auto-Register | Signature Method | Credential Needed |
|----------|--------------|-----------------|-------------------|
| GitHub | Yes | HMAC-SHA256 (`X-Hub-Signature-256`) | `access_token` |
| Stripe | Yes | Timestamp+HMAC (`Stripe-Signature`) | `secret_key` |
| Slack | Yes (with app token) | HMAC-SHA256 (`X-Slack-Signature`) | `signing_secret` + optional `app_token` |
| Discord | No (manual) | Ed25519 (`X-Signature-Ed25519`) | `public_key` |

### Adding a New Provider

1. Create `app/Engine/WebhookRegistrars/YourProviderWebhookRegistrar.php`
   implementing `App\Engine\Contracts\WebhookRegistrar`
2. Add it to `WebhookRegistrarRegistry::REGISTRARS`
3. Add a `case 'yourprovider'` to `WebhookAutoRegistrationService::buildProviderConfig()`
4. Add the signature header mapping in `WebhookReceiverController::verifyProviderSignature()`

---

## URL Stability

The webhook system stores the exact URL used when registering with the provider
in `webhooks.registered_url`. On every activation, it compares this stored URL
against the current callback URL (`config('app.url') . '/api/v1/webhook/' . uuid`).

If they differ (e.g., you moved from `api.old.com` to `api.new.com`):
1. The old webhook is unregistered from the provider
2. A new webhook is registered with the new URL
3. `registered_url` is updated in the DB

This means workflows keep working automatically after domain changes.

---

## Health Monitoring

Run daily to detect silently broken webhooks:

```bash
php artisan webhooks:health-check
```

Use `--dry-run` to report issues without fixing:

```bash
php artisan webhooks:health-check --dry-run
```

Check a specific provider:

```bash
php artisan webhooks:health-check --provider=github
```

**What it detects:**
- Webhooks that were deleted from the provider (token revoked, app deleted, quota hit)
- Webhooks pointing to dead URLs (URL stability failures not yet caught)

**What it does when it finds a missing webhook:**
- Clears the stale `external_webhook_id` from the DB
- Dispatches a `WebhookRegistrationJob` for the workflow
- Logs the repair

The command is scheduled daily at 03:00 in `routes/console.php`.

---

## Database Schema

### `webhooks` table

| Column | Purpose |
|--------|---------|
| `uuid` | Public identifier used in the callback URL (`/api/v1/webhook/{uuid}`) |
| `workflow_id` | The workflow this webhook belongs to |
| `node_id` | Which trigger node in the workflow this corresponds to |
| `provider` | Provider name: `github`, `stripe`, `slack`, `discord`, or null (manual) |
| `external_webhook_id` | The ID assigned by the provider (used for checkExists + unregister) |
| `external_webhook_secret` | Encrypted shared secret for signature verification |
| `registered_url` | The exact URL registered with the provider (for URL stability detection) |
| `provider_config` | JSON — provider-specific metadata (repo owner, events, app_id, etc.) |
| `methods` | Allowed HTTP methods (`["POST"]`) |
| `is_active` | Whether this webhook accepts requests |
| `auth_type` | Manual webhook auth: `none`, `bearer`, `basic`, `header` |
| `auth_config` | Encrypted config for manual auth (token, username/password, etc.) |
| `response_mode` | `immediate` (queue) or `wait` (synchronous result) |
| `response_status` | HTTP status to return to the caller |
| `response_body` | Custom JSON body to return to the caller |
| `call_count` | Total number of times this webhook has been called |
| `last_called_at` | Timestamp of last successful call |

### `workflows` table (webhook-related columns)

| Column | Purpose |
|--------|---------|
| `webhook_status` | `pending` / `active` / `failed` / `deregistering` / null |
| `webhook_status_message` | Error message when `webhook_status = 'failed'` |

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/V1/WebhookReceiverController.php` | Entry point for all incoming webhooks |
| `app/Jobs/WebhookProcessingJob.php` | Async: processes webhook payload, triggers execution |
| `app/Jobs/WebhookRegistrationJob.php` | Async: registers webhooks on provider when workflow activates |
| `app/Jobs/WebhookUnregistrationJob.php` | Async: deletes webhooks on provider when workflow deactivates |
| `app/Services/WebhookAutoRegistrationService.php` | Core registration logic, URL stability, credential resolution |
| `app/Services/WebhookService.php` | Manual webhook CRUD + synchronous `wait` mode handling |
| `app/Engine/Contracts/WebhookRegistrar.php` | Interface all providers must implement |
| `app/Engine/WebhookRegistrars/WebhookRegistrarRegistry.php` | Maps provider name → registrar class |
| `app/Engine/WebhookRegistrars/GitHubWebhookRegistrar.php` | GitHub Hooks API integration |
| `app/Engine/WebhookRegistrars/StripeWebhookRegistrar.php` | Stripe Webhook Endpoints API integration |
| `app/Engine/WebhookRegistrars/SlackWebhookRegistrar.php` | Slack Event Subscriptions integration |
| `app/Engine/WebhookRegistrars/DiscordWebhookRegistrar.php` | Discord Ed25519 verification (manual setup) |
| `app/Console/Commands/WebhookHealthCheckCommand.php` | Daily health check and auto-repair |
| `app/Models/Webhook.php` | Webhook database model |
| `app/Models/Workflow.php` | `activate()` / `deactivate()` dispatch registration jobs |
| `routes/api.php` | Public route: `webhook/{uuid}` |
| `routes/console.php` | Scheduled: `webhooks:health-check` daily |

---

## Common Operations

### Manually re-register a specific workflow's webhooks

```bash
# Deactivate, then re-activate via the API, or directly:
php artisan tinker
>>> $w = \App\Models\Workflow::find(123);
>>> $w->deactivate();  # unregisters
>>> $w->activate();    # re-registers
```

### Check webhook status for a workflow

```bash
php artisan tinker
>>> \App\Models\Workflow::find(123)->only('webhook_status', 'webhook_status_message')
```

### See all active external webhooks

```bash
php artisan tinker
>>> \App\Models\Webhook::whereNotNull('provider')->whereNotNull('external_webhook_id')->get(['uuid', 'provider', 'workflow_id', 'registered_url', 'last_called_at'])
```

### Force health check for one provider

```bash
php artisan webhooks:health-check --provider=github
```
