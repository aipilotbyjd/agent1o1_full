# Architecture Overview

## Design Goals

1. **No vendor lock-in** — everything runs in Docker containers using standard protocols
2. **Zero data loss** — dual backup strategy (local + offsite)
3. **Handle high workflow volumes** — Laravel Horizon with multiple workers
4. **Easy to move providers** — same Docker setup works on Hetzner, Azure, DigitalOcean, any VPS
5. **Low cost** — single server handles medium SaaS load; scale vertically before horizontally

---

## Service Architecture (Single Server)

```
Internet
    │
    ▼
[Nginx :80/:443]
    │  serves static assets directly
    │  proxies PHP requests to
    ▼
[PHP-FPM :9000]          [Laravel Horizon]
    │                          │
    ▼                          ▼
[PostgreSQL :5432]       [Redis :6379]
    │                          │
    ▼                          │
[Persistent Volume]      [Persistent Volume]
```

All services run as Docker containers on one server, managed by Docker Compose.
Nginx and PHP-FPM run inside the same app container managed by Supervisord.
Horizon runs as a separate supervised process within the same container.

---

## Container Layout

### App Container (PHP 8.2-fpm + Nginx + Horizon)
- Built from a multi-stage Dockerfile
- Stage 1: Node 20 — compiles React/Vite frontend assets
- Stage 2: PHP 8.2-fpm-alpine — installs PHP extensions, Composer deps, copies built assets
- Nginx serves static files directly (fast), proxies `.php` requests to PHP-FPM
- Supervisord manages three processes: nginx, php-fpm, horizon
- On startup: waits for DB → runs migrations → caches config → starts serving

### PostgreSQL Container
- Official postgres:17-alpine image
- Data stored in a named Docker volume (survives container restarts and rebuilds)
- Health check ensures app only starts after DB is ready
- Not exposed to the internet — only reachable within Docker network

### Redis Container
- Official redis:7-alpine image
- Password protected
- Data stored in a named Docker volume
- Not exposed to the internet — only reachable within Docker network

---

## Network Security Model

```
Public Internet → Port 80/443 (Nginx only)
                → Port 22 (SSH only)
                → All other ports: BLOCKED by firewall

Internal Docker Network:
  app ←→ pgsql (port 5432)
  app ←→ redis  (port 6379)
  (database ports never exposed to the internet)
```

---

## Data Persistence

| Data | Where Stored | Survives Rebuild? |
|---|---|---|
| Database rows | Docker volume: pgsql-data | Yes |
| Redis data | Docker volume: redis-data | Yes |
| Uploaded files | Docker volume: app-storage | Yes |
| App code | Rebuilt from image | Rebuilt fresh each deploy |
| Config cache | Rebuilt on startup | Rebuilt fresh each deploy |

---

## Workflow Engine Architecture

This app runs a workflow automation engine (similar to n8n). Each workflow execution:
1. Comes in via HTTP webhook or scheduled trigger
2. Gets dispatched to a Laravel Queue job
3. Redis queues the job
4. Horizon worker picks it up and executes the node graph
5. Results are stored in PostgreSQL
6. SSE (Server-Sent Events) streams progress to the frontend

**For high workflow volumes:**
- Horizon manages a pool of workers (configurable count)
- Long-running nodes use Laravel Concurrency or suspendable jobs
- Redis queues are separated by priority (high/default/low)

---

## Why Single Server First

For most SaaS apps at early/medium scale, a single well-configured server is:
- Simpler to manage and debug
- Cheaper (no load balancer, no managed DB fees)
- Easier to back up and restore
- Easier to move between providers

**Scale vertically first** (bigger server) before going multi-server.
See `08-scaling.md` for when and how to scale out.

---

## Environment Variables Strategy

All sensitive configuration lives in `.env` on the server — never in code or Docker images.
Docker Compose reads from `.env` and injects values as environment variables into containers.
This means:
- The same Docker image works in any environment
- Secrets are never baked into images
- Rotating a secret = update `.env` + redeploy
