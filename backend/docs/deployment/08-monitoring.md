# Monitoring & Alerting

Monitoring ensures you know about problems before your users do.

---

## What to Monitor

| Metric | Why It Matters |
|---|---|
| App uptime / HTTP health | Is the app responding? |
| Horizon queue health | Are workflows processing? |
| Disk space | Full disk = app crashes |
| Memory usage | Low memory = slow/crashed containers |
| Failed jobs | Workflows stuck or erroring |
| SSL certificate expiry | Expired cert = users can't access app |
| Database connectivity | DB down = complete outage |

---

## Layer 1 — Free External Uptime Monitoring

Use an external service to ping your app every minute and alert you if it goes down.

### Option A: BetterUptime (Free tier — 10 monitors)
1. Sign up at [betterstack.com/uptime](https://betterstack.com/uptime)
2. Add monitor → URL: `https://yourdomain.com/up`
3. Set alert: Email + SMS when down for 2+ minutes

### Option B: UptimeRobot (Free — 50 monitors, 5-min interval)
1. Sign up at [uptimerobot.com](https://uptimerobot.com)
2. Add HTTP monitor for `https://yourdomain.com/up`
3. Configure email alerts

### Laravel Health Check Endpoint
Laravel has a built-in `/up` endpoint. It returns HTTP 200 when the app and database are healthy.
This is what external monitors should check — not just the homepage.

---

## Layer 2 — Horizon Dashboard (Queue Monitoring)

Laravel Horizon provides a built-in dashboard for monitoring queues and workers.

**Access it at:** `https://yourdomain.com/horizon`

**Secure it** — in `app/Providers/HorizonServiceProvider.php`:
```php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user) {
        return in_array($user->email, [
            'admin@yourdomain.com',
        ]);
    });
}
```

**What to watch in Horizon:**
- **Throughput**: Jobs processed per minute
- **Failed jobs**: Any red number here needs attention
- **Wait time**: How long jobs sit in queue before processing
- **Workers**: How many active Horizon processes

**Alert on failed jobs** — add to your scheduler in `routes/console.php`:
```php
Schedule::call(function () {
    $failedCount = DB::table('failed_jobs')->count();
    if ($failedCount > 10) {
        // Send email or Slack notification
    }
})->everyFiveMinutes();
```

---

## Layer 3 — Server Resource Monitoring

### Check Disk Space
```bash
# Current usage
df -h

# Alert when disk is above 80% (add to crontab)
0 * * * * df / | awk 'NR==2 {if($5+0 > 80) print "DISK ALERT: "$5" used on $(hostname)"}' | \
    grep ALERT | mail -s "Disk Space Alert" your@email.com
```

### Check Memory
```bash
# Current usage
free -h

# Docker container stats
docker stats --no-stream
```

### Automated Monitoring with cron
Add to crontab (`crontab -e`):
```
# Disk space alert (hourly)
0 * * * * df / | awk 'NR==2{gsub(/%/,""); if($5>80) system("echo Disk at "$5"% | mail -s \"Disk Alert\" your@email.com")}'

# Check all containers are running (every 5 min)
*/5 * * * * docker ps --filter "status=exited" --format "{{.Names}}" | \
    xargs -I{} sh -c 'echo "Container {} is down" | mail -s "Container Down" your@email.com'
```

---

## Layer 4 — Laravel Pulse (Built-in Dashboard)

Your app uses **Laravel Pulse** (already configured). It tracks:
- Request performance
- Slow queries
- Exception rates
- Queue depths
- Cache hit rates

**Access it at:** `https://yourdomain.com/pulse`

Secure it the same way as Horizon — restrict to admin emails only.

---

## Layer 5 — Log Monitoring

### View logs in real time
```bash
# All containers
docker compose -f docker-compose.prod.yml logs -f

# App only
docker compose -f docker-compose.prod.yml logs -f app

# Grep for errors
docker compose -f docker-compose.prod.yml logs app | grep -i error
```

### Laravel log file
```bash
docker compose -f docker-compose.prod.yml exec app \
    tail -f storage/logs/laravel.log
```

### Set log level to `error` in production
In `.env`:
```env
LOG_LEVEL=error
```
This means only actual errors are logged — not debug noise.

---

## SSL Certificate Monitoring

Certificates expire every 90 days. Check expiry:
```bash
echo | openssl s_client -connect yourdomain.com:443 2>/dev/null \
    | openssl x509 -noout -dates
```

Add to crontab (weekly alert if expiring within 14 days):
```
0 9 * * 1 certbot certificates 2>/dev/null | grep -A2 "yourdomain.com" | \
    grep "VALID" | awk '{if($NF<14) print}' | \
    mail -s "SSL Expiring Soon" your@email.com
```

---

## Alerting Summary

| What | How | Frequency |
|---|---|---|
| App down | External uptime monitor (BetterUptime) | Every 1 minute |
| Disk full | Cron + email | Hourly |
| Container stopped | Cron + email | Every 5 min |
| Failed jobs > 10 | Laravel scheduler + email | Every 5 min |
| SSL expiring | Cron + email | Weekly |
| Backup failed | Backup script error handler | Daily |
