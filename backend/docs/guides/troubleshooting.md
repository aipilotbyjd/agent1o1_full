# 🔧 Troubleshooting Guide

**Common issues and solutions for LinkFlow**

---

## Overview

This guide helps you diagnose and fix common issues with LinkFlow. Issues are organized by category with symptoms, causes, and solutions.

**General Troubleshooting Approach:**
1. Check logs first
2. Verify environment configuration
3. Test individual components
4. Use debugging tools
5. Search similar issues

---

## Table of Contents

1. [Quick Diagnostics](#quick-diagnostics)
2. [Application Issues](#application-issues)
3. [Database Issues](#database-issues)
4. [Queue & Jobs Issues](#queue--jobs-issues)
5. [Workflow Execution Issues](#workflow-execution-issues)
6. [API & HTTP Issues](#api--http-issues)
7. [Authentication Issues](#authentication-issues)
8. [Performance Issues](#performance-issues)
9. [Webhook Issues](#webhook-issues)
10. [Deployment Issues](#deployment-issues)
11. [Development Issues](#development-issues)
12. [Log Analysis](#log-analysis)
13. [Debugging Tools](#debugging-tools)

---

## Quick Diagnostics

### Health Check Script

```bash
#!/bin/bash
echo "=== LinkFlow Health Check ==="

# Application
echo -e "\n[Application]"
php artisan --version
echo "Environment: $(grep APP_ENV .env | cut -d '=' -f2)"
echo "Debug: $(grep APP_DEBUG .env | cut -d '=' -f2)"

# Database
echo -e "\n[Database]"
php artisan db:show 2>&1 | grep -E "Connection|Database|Host"

# Redis
echo -e "\n[Redis]"
redis-cli ping 2>&1

# Queue
echo -e "\n[Queue Workers]"
sudo supervisorctl status horizon

# Disk Space
echo -e "\n[Disk Space]"
df -h | grep -E "Filesystem|/dev/"

# Services
echo -e "\n[Services]"
echo "Nginx: $(systemctl is-active nginx)"
echo "PHP-FPM: $(systemctl is-active php8.3-fpm)"
echo "PostgreSQL: $(systemctl is-active postgresql)"
echo "Redis: $(systemctl is-active redis)"

# Recent Errors
echo -e "\n[Recent Errors (last 10)]"
tail -n 10 storage/logs/laravel.log | grep ERROR
```

Save as `/root/health-check.sh` and run:
```bash
chmod +x /root/health-check.sh
/root/health-check.sh
```

---

### Check Service Status

```bash
# All services
sudo systemctl status nginx php8.3-fpm postgresql redis

# Queue workers
sudo supervisorctl status

# Check if services are listening
sudo netstat -tlnp | grep -E '80|443|5432|6379|8000'
```

---

## Application Issues

### Issue: White Screen / 500 Error

**Symptoms:**
- Blank page
- HTTP 500 Internal Server Error
- No error message visible

**Diagnosis:**

```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Check Nginx error log
sudo tail -f /var/log/nginx/error.log

# Check PHP-FPM logs
sudo tail -f /var/log/php8.3-fpm.log
```

**Common Causes & Solutions:**

**1. Debug mode disabled:**

```bash
# Temporarily enable debug (NEVER in production!)
php artisan tinker
> config(['app.debug' => true]);
> exit

# Or edit .env
APP_DEBUG=true
php artisan config:clear
```

**2. Permission issues:**

```bash
sudo chown -R www-data:www-data /var/www/linkflow
sudo chmod -R 775 storage bootstrap/cache
```

**3. Missing dependencies:**

```bash
composer install --no-dev --optimize-autoloader
```

**4. Cached config:**

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

---

### Issue: Application Not Starting

**Symptoms:**
- `php artisan serve` fails
- Nginx shows 502 Bad Gateway

**Diagnosis:**

```bash
# Check PHP version
php -v

# Check required extensions
php -m | grep -E "pgsql|redis|mbstring|xml|curl|zip"

# Test PHP-FPM
sudo php-fpm8.3 -t
```

**Solutions:**

**1. Missing PHP extensions:**

```bash
sudo apt install -y php8.3-pgsql php8.3-redis php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath

sudo systemctl restart php8.3-fpm
```

**2. PHP-FPM not running:**

```bash
sudo systemctl start php8.3-fpm
sudo systemctl enable php8.3-fpm
```

**3. Port already in use:**

```bash
# Find process using port 8000
sudo lsof -i :8000

# Kill process
sudo kill -9 <PID>
```

---

### Issue: "Class not found" Error

**Symptoms:**
```
Class 'App\Services\WorkflowService' not found
```

**Solutions:**

```bash
# Regenerate autoload files
composer dump-autoload

# Clear compiled files
php artisan clear-compiled

# Re-optimize
php artisan optimize
```

---

### Issue: "Route not found" Error

**Symptoms:**
```
Route [api.workflows.index] not defined
```

**Solutions:**

```bash
# Clear route cache
php artisan route:clear

# List all routes
php artisan route:list

# Cache routes (production only)
php artisan route:cache
```

---

## Database Issues

### Issue: Database Connection Failed

**Symptoms:**
```
Illuminate\Database\QueryException
COULD NOT find driver (SQL: select * from users)
```

**Diagnosis:**

```bash
# Check PostgreSQL is running
sudo systemctl status postgresql

# Test connection
psql -U linkflow_user -h localhost linkflow_prod

# Check PHP PDO extension
php -m | grep pdo_pgsql
```

**Solutions:**

**1. PostgreSQL not running:**

```bash
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

**2. Wrong credentials in .env:**

```bash
# Verify .env settings
cat .env | grep DB_

# Should match PostgreSQL user
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=linkflow_prod
DB_USERNAME=linkflow_user
DB_PASSWORD=correct_password
```

**3. Missing PDO driver:**

```bash
sudo apt install -y php8.3-pgsql
sudo systemctl restart php8.3-fpm
```

**4. PostgreSQL not listening on correct port:**

```bash
# Check PostgreSQL config
sudo -u postgres psql -c "SHOW port;"

# Edit postgresql.conf if needed
sudo nano /etc/postgresql/17/main/postgresql.conf
# Set: listen_addresses = 'localhost'

sudo systemctl restart postgresql
```

---

### Issue: Migration Failed

**Symptoms:**
```
SQLSTATE[42P01]: Undefined table
SQLSTATE[23505]: Unique violation
```

**Solutions:**

**1. Fresh migration:**

```bash
# Drop all tables and re-migrate (WARNING: DATA LOSS)
php artisan migrate:fresh

# With seeding
php artisan migrate:fresh --seed
```

**2. Rollback and retry:**

```bash
# Rollback last migration
php artisan migrate:rollback

# Rollback all
php artisan migrate:reset

# Re-run
php artisan migrate
```

**3. Check migration status:**

```bash
php artisan migrate:status
```

**4. Fix stuck migration:**

```sql
-- Check migrations table
psql linkflow_prod
SELECT * FROM migrations ORDER BY batch DESC;

-- Remove stuck migration
DELETE FROM migrations WHERE migration = 'xxxx_failed_migration';
```

---

### Issue: "Too many connections"

**Symptoms:**
```
SQLSTATE[08006] too many connections for role "linkflow_user"
```

**Diagnosis:**

```sql
-- Check current connections
psql linkflow_prod
SELECT count(*) FROM pg_stat_activity WHERE usename = 'linkflow_user';

-- Check connection limit
SELECT rolconnlimit FROM pg_roles WHERE rolname = 'linkflow_user';

-- Show active connections
SELECT pid, usename, application_name, state 
FROM pg_stat_activity 
WHERE usename = 'linkflow_user';
```

**Solutions:**

**1. Increase connection limit:**

```sql
-- For user
ALTER ROLE linkflow_user CONNECTION LIMIT 100;

-- For database
ALTER DATABASE linkflow_prod CONNECTION LIMIT 200;
```

**2. Kill idle connections:**

```sql
-- Kill idle connections older than 10 minutes
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE usename = 'linkflow_user'
  AND state = 'idle'
  AND state_change < NOW() - INTERVAL '10 minutes';
```

**3. Fix connection leak in code:**

Check for:
- Missing `DB::disconnect()`
- Long-running queries holding connections
- Improper use of persistent connections

---

### Issue: Slow Queries

**Diagnosis:**

```bash
# Enable query logging in .env
DB_LOG_QUERIES=true

# Check slow query log
tail -f storage/logs/laravel.log | grep "Slow query"
```

```sql
-- PostgreSQL slow query log
ALTER SYSTEM SET log_min_duration_statement = 1000; -- 1 second
SELECT pg_reload_conf();

-- Check logs
sudo tail -f /var/log/postgresql/postgresql-17-main.log
```

**Solutions:**

**1. Add missing indexes:**

```sql
-- Find queries without indexes
EXPLAIN ANALYZE SELECT * FROM executions WHERE workflow_id = 'xxx';

-- Add index if "Seq Scan" appears
CREATE INDEX idx_executions_workflow_id ON executions(workflow_id);
```

**2. Optimize query:**

```php
// ❌ Bad: N+1 query
$workflows = Workflow::all();
foreach ($workflows as $workflow) {
    echo $workflow->workspace->name; // Queries workspace for each workflow
}

// ✅ Good: Eager loading
$workflows = Workflow::with('workspace')->get();
foreach ($workflows as $workflow) {
    echo $workflow->workspace->name; // No extra queries
}
```

**3. Use pagination:**

```php
// ❌ Bad: Load all records
$executions = Execution::all();

// ✅ Good: Paginate
$executions = Execution::paginate(20);
```

---

## Queue & Jobs Issues

### Issue: Queue Jobs Not Processing

**Symptoms:**
- Jobs pile up in `jobs` table
- Workflows don't execute
- Horizon dashboard shows no activity

**Diagnosis:**

```bash
# Check Horizon status
php artisan horizon:status

# Check supervisor
sudo supervisorctl status horizon

# Check queue
php artisan queue:monitor redis:default

# Check failed jobs
php artisan queue:failed
```

**Solutions:**

**1. Horizon not running:**

```bash
# Start Horizon
sudo supervisorctl start horizon

# If supervisor config missing, create it:
sudo nano /etc/supervisor/conf.d/horizon.conf
```

Add:
```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/linkflow/artisan horizon
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/linkflow/storage/logs/horizon.log
stopwaitsecs=3600
```

Reload:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
```

**2. Redis connection issue:**

```bash
# Test Redis
redis-cli ping
# Should return: PONG

# Check Redis password in .env
cat .env | grep REDIS

# Test connection with password
redis-cli -a your_password ping
```

**3. Horizon paused:**

```bash
php artisan horizon:continue
```

**4. Jobs stuck:**

```bash
# Terminate Horizon (will restart via supervisor)
php artisan horizon:terminate

# Or restart supervisor
sudo supervisorctl restart horizon
```

---

### Issue: Jobs Failing Silently

**Diagnosis:**

```bash
# Check failed jobs table
php artisan queue:failed

# Check Horizon logs
tail -f storage/logs/horizon.log

# Check Laravel logs
tail -f storage/logs/laravel.log
```

**Solutions:**

**1. Retry failed jobs:**

```bash
# Retry all
php artisan queue:retry all

# Retry specific job
php artisan queue:retry <job-id>
```

**2. Increase timeout:**

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'timeout' => 600, // Increase from 300 to 600 seconds
        ],
    ],
],
```

**3. Add better error handling:**

```php
// app/Jobs/ExecuteWorkflowJob.php
public function failed(\Throwable $exception)
{
    Log::error('Workflow execution failed', [
        'workflow_id' => $this->workflowId,
        'error' => $exception->getMessage(),
        'trace' => $exception->getTraceAsString(),
    ]);
    
    // Update execution status
    Execution::where('workflow_id', $this->workflowId)
        ->update(['status' => 'failed', 'error' => $exception->getMessage()]);
}
```

---

### Issue: High Queue Memory Usage

**Symptoms:**
- Horizon consuming excessive memory
- Server OOM (Out of Memory) errors

**Diagnosis:**

```bash
# Check Horizon memory
ps aux | grep horizon

# Monitor memory
watch -n 1 'ps aux | grep horizon'
```

**Solutions:**

**1. Set memory limit:**

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'memory' => 256, // MB
        ],
    ],
],
```

**2. Limit max jobs:**

```php
'maxJobs' => 1000, // Restart worker after 1000 jobs
```

**3. Restart Horizon periodically:**

```bash
# Add to crontab
0 */6 * * * php /var/www/linkflow/artisan horizon:terminate
```

---

## Workflow Execution Issues

### Issue: Workflow Not Triggering

**Symptoms:**
- Webhook called but workflow doesn't execute
- Scheduled workflow doesn't run
- Manual execution doesn't start

**Diagnosis:**

```bash
# Check if workflow is active
php artisan tinker
> Workflow::find('workflow-id')->is_active

# Check recent executions
> Execution::latest()->take(5)->get()

# Check webhook logs
tail -f storage/logs/laravel.log | grep webhook

# Check scheduler is running (for scheduled workflows)
ps aux | grep "schedule:run"
```

**Solutions:**

**1. Workflow is inactive:**

```php
php artisan tinker
> $workflow = Workflow::find('workflow-id');
> $workflow->update(['is_active' => true]);
```

**2. Webhook signature mismatch:**

Check webhook security settings and ensure signature is correct.

**3. Scheduler not running (for scheduled workflows):**

```bash
# Add to crontab
crontab -e
```

Add:
```
* * * * * php /var/www/linkflow/artisan schedule:run >> /dev/null 2>&1
```

**4. Queue not processing:**

See [Queue Jobs Not Processing](#issue-queue-jobs-not-processing)

---

### Issue: Workflow Execution Stuck

**Symptoms:**
- Execution status stuck on "running"
- Never completes or fails

**Diagnosis:**

```bash
# Check execution details
php artisan tinker
> $execution = Execution::find('execution-id');
> $execution->status
> $execution->current_node
> $execution->updated_at // When it last updated

# Check for suspended executions
> Execution::where('status', 'suspended')->count()
```

**Solutions:**

**1. Node timed out:**

Check node timeout settings and increase if needed.

**2. External API not responding:**

Check logs for HTTP timeout errors. Increase timeout or add retry logic.

**3. Manual cleanup:**

```php
php artisan tinker
// Mark as failed
> Execution::where('status', 'running')
    ->where('updated_at', '<', now()->subHours(1))
    ->update(['status' => 'failed', 'error' => 'Timed out']);
```

---

### Issue: Node Execution Fails

**Symptoms:**
```
Node execution error: HTTP request failed
Node execution error: Invalid credentials
```

**Diagnosis:**

```bash
# Check execution logs
php artisan tinker
> $execution = Execution::find('execution-id');
> $execution->logs // Array of log entries
> $execution->error
```

**Solutions:**

Depends on node type:

**HTTP Node:**
- Check if external API is accessible
- Verify API credentials
- Check rate limiting

**LLM Node:**
- Verify API key is valid
- Check quota/credits
- Increase timeout

**Database Node:**
- Check database credentials
- Verify table exists
- Check SQL syntax

---

## API & HTTP Issues

### Issue: CORS Errors

**Symptoms:**
```
Access to fetch at 'https://api.yourdomain.com' from origin 'https://app.yourdomain.com' 
has been blocked by CORS policy
```

**Solutions:**

**1. Update CORS config:**

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        env('FRONTEND_URL', 'https://app.yourdomain.com'),
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

**2. Clear config cache:**

```bash
php artisan config:clear
```

**3. Check Nginx config:**

Ensure Nginx isn't stripping CORS headers.

---

### Issue: 404 Not Found (API Endpoint)

**Symptoms:**
- API endpoint returns 404
- Route works locally but not in production

**Diagnosis:**

```bash
# List all routes
php artisan route:list

# Filter by URI
php artisan route:list --path=workflows

# Check route cache
ls -la bootstrap/cache/routes*
```

**Solutions:**

**1. Clear route cache:**

```bash
php artisan route:clear
php artisan route:cache
```

**2. Check route definition:**

```php
// routes/api.php
Route::middleware('auth:api')->group(function () {
    Route::get('/workflows', [WorkflowController::class, 'index']);
});
```

**3. Check Nginx rewrite rules:**

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

---

### Issue: 429 Too Many Requests

**Symptoms:**
```
429 Too Many Requests
Retry-After: 60
```

**Diagnosis:**

```php
// Check rate limit config
php artisan tinker
> config('app.rate_limit')
```

**Solutions:**

**1. Increase rate limit:**

```php
// app/Providers/RouteServiceProvider.php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
});
```

**2. Use separate limits for different endpoints:**

```php
RateLimiter::for('heavy-operation', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()->id);
});

Route::middleware('throttle:heavy-operation')->post('/execute', ...);
```

---

## Authentication Issues

### Issue: "Unauthenticated" Error

**Symptoms:**
```
{"message": "Unauthenticated."}
```

**Diagnosis:**

```bash
# Check token
curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://api.yourdomain.com/api/v1/me
```

**Solutions:**

**1. Token expired:**

Request new token via login endpoint.

**2. Token revoked:**

Check `oauth_access_tokens` table:

```sql
SELECT * FROM oauth_access_tokens WHERE user_id = 'user-id' ORDER BY created_at DESC;
```

**3. Passport not configured:**

```bash
php artisan passport:install
php artisan passport:keys
```

---

### Issue: Login Fails with Correct Credentials

**Symptoms:**
- Correct password rejected
- No error message

**Diagnosis:**

```php
php artisan tinker
> $user = User::where('email', 'test@example.com')->first();
> Hash::check('password', $user->password)
// Should return true if password is correct
```

**Solutions:**

**1. Password hash mismatch:**

Reset password:

```php
php artisan tinker
> $user = User::where('email', 'test@example.com')->first();
> $user->password = Hash::make('newpassword');
> $user->save();
```

**2. Email not verified:**

Check if email verification is required:

```php
> $user->email_verified_at
// Should not be null

// Verify manually
> $user->update(['email_verified_at' => now()]);
```

---

## Performance Issues

### Issue: Slow Response Times

**Diagnosis:**

```bash
# Check PHP opcache
php -i | grep opcache

# Check response time
curl -w "@curl-format.txt" -o /dev/null -s https://api.yourdomain.com/api/v1/workflows
```

Create `curl-format.txt`:
```
time_namelookup:  %{time_namelookup}s
time_connect:  %{time_connect}s
time_appconnect:  %{time_appconnect}s
time_pretransfer:  %{time_pretransfer}s
time_redirect:  %{time_redirect}s
time_starttransfer:  %{time_starttransfer}s
time_total:  %{time_total}s
```

**Solutions:**

**1. Enable opcache:**

```ini
# /etc/php/8.3/fpm/php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
```

**2. Cache config/routes/views:**

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**3. Use Redis for cache:**

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

**4. Optimize autoloader:**

```bash
composer install --optimize-autoloader --no-dev
```

**5. Add database indexes:**

See [Slow Queries](#issue-slow-queries)

---

### Issue: High Memory Usage

**Diagnosis:**

```bash
# Check PHP memory limit
php -i | grep memory_limit

# Monitor processes
top -o %MEM
```

**Solutions:**

**1. Increase PHP memory limit:**

```ini
# /etc/php/8.3/fpm/php.ini
memory_limit = 512M
```

**2. Use chunking for large datasets:**

```php
// ❌ Bad
$executions = Execution::all(); // Loads all into memory

// ✅ Good
Execution::chunk(1000, function ($executions) {
    foreach ($executions as $execution) {
        // Process
    }
});
```

**3. Unset large variables:**

```php
$data = SomeModel::all();
// Process $data
unset($data); // Free memory
```

---

## Webhook Issues

### Issue: Webhook Not Receiving Data

**Symptoms:**
- Webhook endpoint returns 200 but workflow doesn't execute
- Missing data in execution

**Diagnosis:**

```bash
# Check webhook logs
tail -f storage/logs/laravel.log | grep webhook

# Test webhook manually
curl -X POST https://api.yourdomain.com/webhooks/xxx \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

**Solutions:**

**1. Check webhook URL:**

Ensure webhook URL is correct and publicly accessible.

**2. Check signature verification:**

If webhook requires signature, ensure it's correct.

**3. Check request parsing:**

```php
// Log incoming webhook data
Log::info('Webhook received', [
    'headers' => $request->headers->all(),
    'body' => $request->getContent(),
    'parsed' => $request->all(),
]);
```

---

## Deployment Issues

### Issue: Deployment Script Fails

**Common causes:**

**1. Permission denied:**

```bash
sudo chown -R www-data:www-data /var/www/linkflow
```

**2. Git pull fails:**

```bash
# Reset local changes
git reset --hard
git pull origin main
```

**3. Composer install fails:**

```bash
# Clear cache
composer clear-cache

# Install with verbose output
composer install -vvv
```

**4. Migration fails:**

See [Migration Failed](#issue-migration-failed)

---

## Development Issues

### Issue: Artisan Commands Not Working

**Symptoms:**
```
Command "xyz" is not defined
```

**Solutions:**

```bash
# Clear bootstrap cache
php artisan clear-compiled

# Recreate autoload files
composer dump-autoload

# List available commands
php artisan list
```

---

### Issue: Tinker Not Working

**Solutions:**

```bash
# Install tinker
composer require laravel/tinker

# Clear config
php artisan config:clear

# Run tinker
php artisan tinker
```

---

## Log Analysis

### Laravel Logs

```bash
# Real-time monitoring
tail -f storage/logs/laravel.log

# Last 100 lines
tail -n 100 storage/logs/laravel.log

# Search for errors
grep ERROR storage/logs/laravel.log

# Count errors today
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log | grep ERROR | wc -l

# Find specific error
grep -A 10 "Class not found" storage/logs/laravel.log
```

### Nginx Logs

```bash
# Access log
sudo tail -f /var/log/nginx/access.log

# Error log
sudo tail -f /var/log/nginx/error.log

# 404 errors
grep " 404 " /var/log/nginx/access.log

# 500 errors
grep " 500 " /var/log/nginx/access.log
```

### PostgreSQL Logs

```bash
# Location
sudo tail -f /var/log/postgresql/postgresql-17-main.log

# Slow queries
grep "duration" /var/log/postgresql/postgresql-17-main.log
```

### Horizon Logs

```bash
tail -f storage/logs/horizon.log
```

---

## Debugging Tools

### Laravel Tinker

Interactive REPL for Laravel:

```bash
php artisan tinker
```

```php
// Get user
$user = User::find('user-id');

// Run query
DB::table('workflows')->where('is_active', true)->count();

// Test service
app(WorkflowService::class)->execute('workflow-id', []);

// Check config
config('database.connections.pgsql');

// Clear cache
Cache::flush();
```

---

### Laravel Telescope (Development)

Powerful debugging tool (install in development only):

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Access at: `http://localhost/telescope`

Features:
- Request inspector
- Query profiler
- Job monitoring
- Exception tracking
- Log viewer

---

### Database Query Debugging

```php
// Enable query log
DB::enableQueryLog();

// Run queries
$workflows = Workflow::where('is_active', true)->get();

// View queries
dd(DB::getQueryLog());
```

---

### HTTP Request Debugging

```bash
# Using curl with verbose output
curl -v -X POST https://api.yourdomain.com/api/v1/workflows \
  -H "Authorization: Bearer token" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test"}'

# Save response headers
curl -D headers.txt https://api.yourdomain.com/api/v1/workflows

# Follow redirects
curl -L https://api.yourdomain.com/api/v1/workflows
```

---

### Network Debugging

```bash
# Check if port is open
telnet api.yourdomain.com 443

# Test SSL certificate
openssl s_client -connect api.yourdomain.com:443

# Check DNS
nslookup api.yourdomain.com
dig api.yourdomain.com

# Trace route
traceroute api.yourdomain.com
```

---

## Getting Help

### Before Asking for Help

1. **Check logs** (Laravel, Nginx, PostgreSQL, Horizon)
2. **Search documentation** (this guide, Laravel docs)
3. **Google the error message**
4. **Check GitHub issues** (if using open source packages)
5. **Try to reproduce** in isolated environment

### When Asking for Help

Provide:

1. **Error message** (full stack trace)
2. **Steps to reproduce**
3. **Expected vs actual behavior**
4. **Environment** (PHP version, Laravel version, OS)
5. **Relevant code** (controller, model, route)
6. **Logs** (last 50 lines)
7. **What you've tried** (solutions attempted)

---

**Most issues can be solved by checking logs and verifying configuration. When in doubt, check the logs first!** 🔧

*Last Updated: December 2024*
