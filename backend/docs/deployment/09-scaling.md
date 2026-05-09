# Scaling for High Workflow Volumes

This platform is a workflow automation engine. Scaling it correctly means tuning
the queue workers, not just adding more servers.

---

## Understanding the Bottlenecks

When workflows slow down, the bottleneck is almost always one of these:

1. **Horizon worker count** — not enough workers to process the queue
2. **Database queries** — missing indexes or inefficient queries
3. **Server memory** — containers being killed (OOMKilled)
4. **Server CPU** — compute-intensive workflow nodes

Identify which one before scaling:

```bash
# Check if queue is backing up (jobs waiting)
docker compose -f docker-compose.prod.yml exec app \
    php artisan horizon:status

# Check container memory/CPU
docker stats

# Check slow queries
docker compose -f docker-compose.prod.yml exec app \
    php artisan db:monitor
```

---

## Level 1 — Tune Horizon Workers (Free, Do This First)

Horizon controls how many parallel workers process jobs. Your current config
may be too conservative. Edit `config/horizon.php`:

```php
'environments' => [
    'production' => [
        // High-priority queue — webhook triggers, user-facing jobs
        'supervisor-high' => [
            'connection' => 'redis',
            'queue' => ['high'],
            'balance' => 'auto',
            'minProcesses' => 2,
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 60,
        ],

        // Default queue — standard workflow execution
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'minProcesses' => 2,
            'maxProcesses' => 20,
            'balanceMaxShift' => 2,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 300,
        ],

        // Low queue — reports, exports, non-urgent tasks
        'supervisor-low' => [
            'connection' => 'redis',
            'queue' => ['low'],
            'balance' => 'simple',
            'processes' => 2,
            'tries' => 3,
            'timeout' => 600,
        ],
    ],
],
```

With `balance => auto`, Horizon automatically scales workers between `minProcesses`
and `maxProcesses` based on actual queue depth. No manual intervention needed.

After changing, redeploy:
```bash
./deploy.sh
```

---

## Level 2 — Vertical Scaling (Bigger Server)

When Horizon is tuned but the server is still struggling, upgrade the server.
This is cheap and takes ~10 minutes with no code changes.

### Hetzner Upgrade Path

| Server | vCPU | RAM | Price | Good for |
|---|---|---|---|---|
| CX22 | 2 | 4GB | €3.79/mo | Up to ~500 workflows/day |
| CX32 | 4 | 8GB | €6.32/mo | Up to ~2,000 workflows/day |
| CX42 | 8 | 16GB | €13.01/mo | Up to ~10,000 workflows/day |
| CX52 | 16 | 32GB | €24.96/mo | Up to ~50,000 workflows/day |

### How to Upgrade on Hetzner
1. Hetzner Cloud Console → Your Server → **Rescale**
2. Pick larger size → Confirm
3. Server reboots (takes ~2 minutes)
4. Docker containers restart automatically (`restart: unless-stopped`)
5. Done — no code changes

---

## Level 3 — Database Optimization

Before scaling hardware, make sure the database isn't the bottleneck.

### Add Missing Indexes
Workflow execution stores a lot of data. Make sure these columns are indexed
(check your migrations, add if missing):

```php
// In a new migration
Schema::table('workflow_executions', function (Blueprint $table) {
    $table->index(['workflow_id', 'status', 'created_at']);
    $table->index(['status', 'created_at']);
});

Schema::table('jobs', function (Blueprint $table) {
    $table->index(['queue', 'reserved_at']);
});
```

### Enable PostgreSQL Query Logging (Temporarily for Debugging)
```bash
docker compose -f docker-compose.prod.yml exec pgsql \
    psql -U myapp -c "ALTER SYSTEM SET log_min_duration_statement = 500;"
docker compose -f docker-compose.prod.yml exec pgsql \
    psql -U myapp -c "SELECT pg_reload_conf();"
```
This logs any query taking over 500ms. Check logs, add indexes, then disable.

### PostgreSQL Tuning for 4GB RAM Server
Connect to PostgreSQL and run:
```sql
ALTER SYSTEM SET shared_buffers = '1GB';
ALTER SYSTEM SET effective_cache_size = '3GB';
ALTER SYSTEM SET work_mem = '16MB';
ALTER SYSTEM SET maintenance_work_mem = '256MB';
ALTER SYSTEM SET max_connections = 100;
SELECT pg_reload_conf();
```

---

## Level 4 — Redis Tuning

For high job volumes, tune Redis memory policy:

Add to `docker-compose.prod.yml` Redis service:
```yaml
redis:
    command: >
        redis-server
        --requirepass ${REDIS_PASSWORD}
        --maxmemory 512mb
        --maxmemory-policy allkeys-lru
        --save ""
        --appendonly no
```

`maxmemory-policy allkeys-lru` — when Redis is full, evict least-recently-used keys.
Disabling `save` and `appendonly` makes Redis faster (queue data is transient anyway).

---

## Level 5 — Separate Database Server (Advanced)

When a single server can't handle both app + database:

1. Provision a second server (Hetzner CX22 or CX32)
2. Install PostgreSQL only on that server
3. Configure PostgreSQL to accept connections from your app server IP
4. Update `DB_HOST` in your app's `.env` to the database server IP
5. Ensure firewall on DB server allows port 5432 only from your app server IP

```bash
# On database server — allow only app server
sudo ufw allow from APP_SERVER_IP to any port 5432
```

---

## Level 6 — Horizontal Scaling (Multiple App Servers)

Only needed at very high scale (100k+ workflows/day). Requires:
- A load balancer (Hetzner Load Balancer — €5.83/mo)
- Shared file storage (for uploaded files)
- Separate database server
- All app servers point to same DB and Redis

This is complex — exhaust vertical scaling first.

---

## Scaling Decision Tree

```
Workflows processing slowly?
        │
        ▼
Check Horizon dashboard
        │
        ├── Queue depth > 1000? → Increase maxProcesses in horizon.php
        │
        ├── Workers maxed but slow? → Check server CPU/RAM
        │                              → Upgrade server (Level 2)
        │
        ├── DB queries > 500ms? → Add indexes (Level 3)
        │
        └── Memory > 80%? → Upgrade server RAM (Level 2)
```

---

## Workflow-Specific Optimization Tips

### Long-Running Nodes (AI, HTTP requests, external APIs)
- Set appropriate `timeout` in Horizon supervisor config (e.g., 300s for AI nodes)
- Use `tries` wisely — 3 retries with exponential backoff
- Put long-running jobs on a separate `slow` queue with fewer workers

### Webhook Throughput
- Webhooks should queue immediately and return 200 fast
- Never do heavy processing in the HTTP request cycle
- Ensure `high` queue workers are plentiful for webhook jobs

### Scheduled Triggers
- Laravel scheduler runs every minute via cron
- For sub-minute scheduling, use queue-based delays instead
