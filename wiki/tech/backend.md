# Backend Tech Stack

**TL;DR**: Laravel 12 / PHP 8.5 monolith with Horizon queues, Passport OAuth, Cashier billing, and Pulse monitoring.

---

## Core Packages

| Package | Version | Role |
|---------|---------|------|
| `laravel/framework` | v12 | Application framework |
| `php` | 8.5.4 | Runtime |
| `laravel/passport` | v13 | OAuth2 server (API auth via Bearer tokens) |
| `laravel/cashier` | v16 | Stripe billing |
| `laravel/horizon` | v5 | Redis queue dashboard and worker |
| `laravel/pulse` | v1 | Performance monitoring dashboard |
| `laravel/pail` | v1 | Real-time log tailing |
| `laravel/ai` | v0 | AI integration (early access) |
| `laravel/mcp` | v0 | MCP server (early access) |
| `livewire/livewire` | v4 | Server-side reactive UI (admin/internal pages) |
| `pestphp/pest` | v4 | Test framework |
| `laravel/pint` | v1 | Code formatter |
| `tailwindcss` | v4 | Styling (blade/livewire views) |

## Directory Structure

```
backend/
├── app/
│   ├── Console/Commands/   # Artisan commands
│   ├── Enums/              # PHP 8 backed enums
│   ├── Http/
│   │   ├── Controllers/Api/V1/   # REST API controllers
│   │   └── Middleware/           # Auth, workspace.role, etc.
│   ├── Jobs/               # Queued jobs
│   ├── Mail/               # Mailable classes
│   ├── Models/             # Eloquent models
│   ├── Providers/          # Service providers
│   └── Traits/             # HasUuid, ApiResponse, FiltersListQuery
├── bootstrap/
│   └── app.php             # Middleware & routing registration (Laravel 12 style)
├── routes/
│   ├── api.php             # All REST API routes (LinkFlow v1)
│   ├── web.php             # Web routes (minimal)
│   └── console.php         # Scheduled commands
└── database/
    ├── migrations/
    ├── factories/
    └── seeders/
```

## Auth Strategy

- **Laravel Passport** — issues Bearer tokens for the React SPA
- API versioned under `/api/v1/`
- Public routes: webhooks, OAuth callback, email verify
- Authenticated routes: everything else
- Workspace-scoped routes: require `workspace.role` middleware

## API Conventions

- Trait `ApiResponse` — standardised JSON response format
- Trait `FiltersListQuery` — reusable filtering/sorting for list endpoints
- Trait `HasUuid` — UUIDs as primary keys across all models
- Form Request classes for all validation
- Eloquent Resources for all API responses

## Key Scheduled Commands

| Command | Frequency |
|---------|-----------|
| `workflows:schedule-cron` | Every minute |
| `workflows:poll` | Every minute |
| `billing:snapshot-daily-usage` | Daily 00:05 |
| `executions:prune` | Daily 02:00 |
| `executions:archive` | Daily 02:30 |
| `webhooks:health-check` | Daily 03:00 |
| `horizon:snapshot` | Every 5 minutes |
| `admin:health-check` | Every 5 minutes |
| `RefreshOAuthTokenJob` | Daily 01:00 |

## Testing

- Pest 4 for all tests
- Run: `php artisan test --compact`
- Every code change must have a test
