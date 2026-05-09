# CI/CD Pipeline — GitHub Actions

Automate your deployment so every push to `main` deploys to production automatically.

---

## How It Works

```
You push code to GitHub (main branch)
        │
        ▼
GitHub Actions triggers
        │
        ├── Run tests (Pest)
        ├── Build Docker image
        └── SSH into server → run deploy.sh
```

If tests fail, deployment is skipped. Nothing broken ever reaches production.

---

## Prerequisites

1. Your code is on GitHub
2. You have SSH access to your server
3. You have a private/public SSH key pair for the deploy user

---

## Step 1 — Add Secrets to GitHub

Go to your repo → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

Add these secrets:

| Secret Name | Value |
|---|---|
| `SERVER_HOST` | Your server IP address |
| `SERVER_USER` | `deploy` |
| `SERVER_SSH_KEY` | Contents of `~/.ssh/id_rsa` (private key) |
| `SERVER_PORT` | `22` |
| `DEPLOY_PATH` | `/home/deploy/YOUR_REPO` |

---

## Step 2 — Create the GitHub Actions Workflow

Create this file in your repo: `.github/workflows/deploy.yml`

```yaml
name: Test & Deploy

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    name: Run Tests
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:17
        env:
          POSTGRES_DB: testing
          POSTGRES_USER: testing
          POSTGRES_PASSWORD: testing
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 5s
          --health-timeout 5s
          --health-retries 10

      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 5s
          --health-timeout 5s
          --health-retries 10

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_pgsql, redis, zip, bcmath, mbstring, intl, pcntl
          coverage: none

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install PHP dependencies
        run: composer install --no-dev --optimize-autoloader --no-interaction

      - name: Install Node dependencies
        run: npm ci

      - name: Build frontend assets
        run: npm run build

      - name: Copy environment file
        run: cp .env.example .env

      - name: Configure test environment
        run: |
          php artisan key:generate
          sed -i 's/DB_CONNECTION=sqlite/DB_CONNECTION=pgsql/' .env
          echo "DB_HOST=127.0.0.1" >> .env
          echo "DB_PORT=5432" >> .env
          echo "DB_DATABASE=testing" >> .env
          echo "DB_USERNAME=testing" >> .env
          echo "DB_PASSWORD=testing" >> .env
          echo "REDIS_HOST=127.0.0.1" >> .env
          echo "QUEUE_CONNECTION=redis" >> .env
          echo "CACHE_STORE=redis" >> .env

      - name: Run migrations
        run: php artisan migrate --force

      - name: Run tests
        run: php artisan test --parallel

  deploy:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: test
    if: github.ref == 'refs/heads/main' && github.event_name == 'push'

    steps:
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1.0.0
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SERVER_SSH_KEY }}
          port: ${{ secrets.SERVER_PORT }}
          script: |
            cd ${{ secrets.DEPLOY_PATH }}
            git pull origin main
            docker compose -f docker-compose.prod.yml up -d --build --remove-orphans
            echo "Deployment complete at $(date)"
```

---

## Step 3 — Add Deploy SSH Key to Server

The GitHub Actions runner needs to SSH into your server. 
Create a dedicated deploy key (separate from your personal key):

```bash
# On your local machine
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/deploy_key -N ""

# Copy the public key to the server
ssh-copy-id -i ~/.ssh/deploy_key.pub deploy@YOUR_SERVER_IP

# Copy the private key contents — paste into GitHub secret SERVER_SSH_KEY
cat ~/.ssh/deploy_key
```

---

## What Happens on Each Push

### To `main` branch:
1. Spins up a test environment with PostgreSQL and Redis
2. Installs dependencies, builds frontend
3. Runs all Pest tests in parallel
4. If tests pass: SSHs into your server, pulls code, rebuilds Docker image, restarts containers
5. If tests fail: stops, deployment is skipped, you get an email from GitHub

### To any other branch (pull request):
1. Runs tests only — no deployment
2. Shows pass/fail status on the pull request

---

## Deployment Time Estimates

| Step | Time |
|---|---|
| Test suite | 2–5 minutes |
| Docker image rebuild | 3–8 minutes (first time longer) |
| Container restart | 30–60 seconds |
| **Total** | **~10 minutes** |

---

## Rollback if Something Goes Wrong

```bash
# SSH into server
ssh deploy@YOUR_SERVER_IP
cd YOUR_REPO

# See recent commits
git log --oneline -5

# Go back to a specific commit
git checkout COMMIT_HASH

# Redeploy the old version
docker compose -f docker-compose.prod.yml up -d --build

# When fixed, go back to main
git checkout main
```

---

## Optional: Slack / Discord Notifications

Add to the end of your `deploy.yml`:

```yaml
      - name: Notify Slack on success
        if: success()
        uses: slackapi/slack-github-action@v1.24.0
        with:
          payload: '{"text":"Deployment successful for ${{ github.repository }} by ${{ github.actor }}"}'
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}

      - name: Notify Slack on failure
        if: failure()
        uses: slackapi/slack-github-action@v1.24.0
        with:
          payload: '{"text":"Deployment FAILED for ${{ github.repository }} — check GitHub Actions"}'
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
```
