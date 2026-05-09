# Production Deployment Plan

This folder contains the complete production deployment plan for this SaaS workflow automation platform.
It covers two cloud providers (Hetzner and Azure), full security hardening, zero data loss backups,
monitoring, CI/CD, and scaling for high workflow volumes.

## Documents

| File | What It Covers |
|---|---|
| [01-architecture.md](./01-architecture.md) | Infrastructure design, service layout, why each decision was made |
| [02-docker-configuration.md](./02-docker-configuration.md) | Every Docker file with full production config and explanation |
| [03-hetzner-deployment.md](./03-hetzner-deployment.md) | Step-by-step Hetzner VPS deployment |
| [04-azure-deployment.md](./04-azure-deployment.md) | Step-by-step Azure deployment using free tier services |
| [05-aws-deployment.md](./05-aws-deployment.md) | Step-by-step AWS EC2 deployment using free tier services |
| [06-security-hardening.md](./06-security-hardening.md) | Full security checklist — firewall, SSH, secrets, app layer |
| [07-backup-strategy.md](./07-backup-strategy.md) | Local + offsite backups, restore procedures, retention policy |
| [08-monitoring.md](./08-monitoring.md) | Uptime, error alerts, queue health, disk space monitoring |
| [09-scaling.md](./09-scaling.md) | Handling high workflow volumes, Horizon tuning, vertical/horizontal scaling |
| [10-cicd.md](./10-cicd.md) | GitHub Actions pipeline — test, build, deploy automatically |

## Recommended Reading Order

**For first deployment:** 01 → 02 → 03 or 04 or 05 → 06 → 07 → 08

**For scaling a running app:** 09 → 08 → 10

**For moving between providers:** 02 → 03 or 04 or 05 → 07 (restore section)

## Stack Summary

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.2) |
| Frontend | React + Vite + Tailwind CSS v4 |
| Database | PostgreSQL 17 |
| Queue / Cache | Redis 7 |
| Background Jobs | Laravel Horizon |
| Web Server | Nginx |
| Process Manager | Supervisord |
| Containerization | Docker + Docker Compose |
| CI/CD | GitHub Actions |

## Provider Comparison

| | Hetzner VPS | Azure VM (Free Tier) |
|---|---|---|
| Cost | €3.79–€14/mo | Free 12 months, then ~€15/mo |
| RAM | 4–8GB | 1GB (B1s free) or 4GB (B2s paid) |
| Control | Full root access | Full root access |
| Best for | Long-term production | Trying out / first 12 months |
| Moving away | Copy Docker + pg_dump | Copy Docker + pg_dump |
