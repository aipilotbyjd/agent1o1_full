# 📚 LinkFlow Documentation

**Complete documentation for understanding and developing the LinkFlow workflow automation platform**

---

## 🎯 Quick Start by Role

### 👨‍💼 Product Manager / Business Owner
1. [Project Overview](./core/01-project-overview.md) - What LinkFlow is and what it can do
2. [Pricing & Roles](./planning/pricing-and-roles.md) - Business model and pricing
3. [Roadmap](./planning/roadmap.md) - Future features and priorities

### 👨‍💻 Backend Developer
1. [Project Overview](./core/01-project-overview.md) - System overview
2. [Architecture](./core/02-architecture.md) - Technical architecture deep dive
3. [Workflow Engine](./core/03-workflow-engine.md) - Engine internals
4. [Developer Handbook](./core/04-developer-handbook.md) - Common tasks and patterns

### 🎨 Frontend Developer
1. [Project Overview](./core/01-project-overview.md) - System overview
2. [Frontend Integration](./frontend/README.md) - Main integration guide
3. [Frontend Modules](./frontend/modules/) - 15 detailed API integration modules

### 🧪 QA Engineer
1. [Testing Guide](./guides/testing.md) - Complete testing reference
2. [Project Overview](./core/01-project-overview.md) - What to test
3. [API Reference](./reference/api.md) - API endpoints for testing

### 🏗️ DevOps Engineer
1. [Deployment Guide](./guides/deployment.md) - Complete deployment reference
2. [Security Guide](./guides/security.md) - Security best practices
3. [Deployment Details](./deployment/) - Platform-specific guides (AWS, Azure, Hetzner)
4. [Troubleshooting](./guides/troubleshooting.md) - Common issues and solutions

---

## 📁 Documentation Structure

### 📘 [Core Documentation](./core/)
Essential guides for understanding LinkFlow

| Guide | Description | Size | Time |
|-------|-------------|------|------|
| [01. Project Overview](./core/01-project-overview.md) | System overview, concepts, architecture | ~40 KB | 30 min |
| [02. Architecture](./core/02-architecture.md) | Technical architecture deep dive | ~45 KB | 45 min |
| [03. Workflow Engine](./core/03-workflow-engine.md) | Engine internals and execution flow | ~25 KB | 40 min |
| [04. Developer Handbook](./core/04-developer-handbook.md) | Common tasks, patterns, best practices | ~25 KB | 20 min |

**Start with:** Project Overview → Architecture → Developer Handbook

---

### 🔧 [Guides](./guides/)
Practical how-to guides

| Guide | Description | Size | Time |
|-------|-------------|------|------|
| [Deployment](./guides/deployment.md) | Complete deployment reference (Docker, VPS, cloud) | 22 KB | 40 min |
| [Security](./guides/security.md) | Authentication, encryption, compliance, security best practices | 34 KB | 50 min |
| [Testing](./guides/testing.md) | Unit, feature, API testing with Pest PHP | 30 KB | 45 min |
| [Troubleshooting](./guides/troubleshooting.md) | Common issues, diagnostics, debugging | 25 KB | 50 min |
| [Webhooks](./guides/webhooks.md) | Webhook architecture and implementation | ~10 KB | 15 min |

**Start with:** Deployment → Security → Testing

---

### 📚 [Reference](./reference/)
API documentation and technical reference

| Reference | Description |
|-----------|-------------|
| [API Reference](./reference/api.md) | Complete REST API documentation |
| [Node Reference](./reference/nodes.md) | All workflow node types and configs |
| [Database Schema](./reference/database-schema.md) | Complete database schema (40+ tables) |
| [Postman Guide](./reference/postman.md) | API testing with Postman collections |

**Postman Collections:** [API Collection](./Agent1o1-API.postman_collection.json) | [Environment](./Agent1o1-Local.postman_environment.json)

---

### 🎨 [Frontend Integration](./frontend/)
Complete React frontend integration guides

| Guide | Description |
|-------|-------------|
| [Main Guide](./frontend/README.md) | Tech stack, setup, project structure, quick reference |

**15 Detailed Modules:**
1. [Authentication](./frontend/modules/01-authentication.md) - Login, register, password reset
2. [Workspace Management](./frontend/modules/02-workspace-management.md) - CRUD, switching
3. [Workflow Editor](./frontend/modules/03-workflow-editor.md) - React Flow integration
4. [Workflow Management](./frontend/modules/04-workflow-management.md) - List, create, update
5. [Execution Dashboard](./frontend/modules/05-execution-dashboard.md) - Monitor, logs, retry
6. [Credentials](./frontend/modules/06-credentials.md) - API keys, OAuth2
7. [Variables & Tags](./frontend/modules/07-variables-tags.md) - Variable management
8. [Webhooks](./frontend/modules/08-webhooks.md) - Webhook configuration
9. [Templates](./frontend/modules/09-templates.md) - Template marketplace
10. [Team Management](./frontend/modules/10-team.md) - Invitations, roles
11. [Notifications](./frontend/modules/11-notifications.md) - In-app notifications
12. [Settings](./frontend/modules/12-settings.md) - User & workspace settings
13. [Analytics](./frontend/modules/13-analytics.md) - Statistics & monitoring
14. [AI Agents](./frontend/modules/14-agents.md) - Agent system integration
15. [Advanced Workflows](./frontend/modules/15-advanced-workflow.md) - Versioning, sharing

---

### 🚀 [Deployment](./deployment/)
Platform-specific deployment guides

| Guide | Description |
|-------|-------------|
| [Architecture Overview](./deployment/01-architecture.md) | Infrastructure design |
| [Docker Configuration](./deployment/02-docker-configuration.md) | Production Docker setup |
| [Hetzner Deployment](./deployment/03-hetzner-deployment.md) | VPS deployment (€3.79/mo) |
| [Azure Deployment](./deployment/04-azure-deployment.md) | Azure VM deployment |
| [AWS Deployment](./deployment/05-aws-deployment.md) | AWS EC2 deployment |
| [Security Hardening](./deployment/06-security-hardening.md) | Firewall, SSH, secrets |
| [Backup Strategy](./deployment/07-backup-strategy.md) | Backups and restore |
| [Monitoring](./deployment/08-monitoring.md) | Uptime, alerts, health checks |
| [Scaling](./deployment/09-scaling.md) | Horizontal/vertical scaling |
| [CI/CD](./deployment/10-cicd.md) | GitHub Actions pipeline |

---

### 📋 [Planning](./planning/)
Project planning, roadmap, and status

| Document | Description |
|----------|-------------|
| [Roadmap](./planning/roadmap.md) | Future features and priorities |
| [Feature Status](./planning/feature-status.md) | Implementation status tracker |
| [Pricing & Roles](./planning/pricing-and-roles.md) | Business model, tiers, permissions |
| [S3 Log Archiving](./planning/s3-log-archiving-plan.md) | Cold storage design for execution logs |
| [Feature Suggestions](./planning/feature-suggestions.md) | Community ideas and proposals |
| [AI Plans](./planning/ai-master-plan.md) | AI/ML feature roadmap |

---

### 📦 [Archive](./archive/)
Historical documents and completed milestones

- [Documentation Complete](./archive/documentation-complete.md)
- [Implementation Milestones](./archive/implementation-complete.md)
- [P0 Nodes Complete](./archive/p0-nodes-implementation-complete.md)

---

## 🎯 Learning Paths

### Path 1: Understanding the System (2-3 hours)
```
1. Read Project Overview (30 min)
2. Skim Architecture (20 min)
3. Read "How Workflows Execute" section (15 min)
4. Explore Workflow Engine guide (15 min)
5. Browse codebase (1-2 hours)
```

### Path 2: Starting Development (1 day)
```
1. Read Project Overview (30 min)
2. Follow Developer Handbook setup (2 hours)
3. Read "Common Development Tasks" (1 hour)
4. Make first code change (2 hours)
5. Write tests (1 hour)
6. Code review (30 min)
```

### Path 3: Frontend Integration (2-3 days)
```
1. Read Frontend Integration guide (15 min)
2. Set up React project (2 hours)
3. Implement Phase 1: Auth + Workspaces (5 hours)
4. Implement Phase 2-6 (remaining modules over 2-3 days)
```

### Path 4: Production Deployment (4-6 hours)
```
1. Read Deployment Guide (40 min)
2. Choose platform & follow guide (2-3 hours)
3. Apply Security Hardening (1 hour)
4. Set up Backups & Monitoring (1 hour)
5. Load testing & optimization (1-2 hours)
```

### Path 5: Debugging Issues (30 min - 2 hours)
```
1. Check Troubleshooting Guide (10 min)
2. Review logs (10 min)
3. Check Workflow Engine guide for flow (15 min)
4. Use debugging tools (15 min)
5. Apply fix and test (30+ min)
```

---

## 📊 Documentation Statistics

| Category | Files | Total Size | Reading Time |
|----------|-------|------------|--------------|
| Core | 4 | ~130 KB | ~2.5 hours |
| Guides | 5 | ~125 KB | ~3 hours |
| Reference | 4 | ~120 KB | ~2.5 hours |
| Frontend | 16 | ~270 KB | ~6 hours |
| Deployment | 11 | ~100 KB | ~2.5 hours |
| Planning | 9 | ~80 KB | ~2 hours |
| **TOTAL** | **49+** | **~825 KB** | **~18.5 hours** |

---

## 🔍 Quick Reference

### Key Files in Codebase

**Workflow Engine:**
- `/app/Engine/WorkflowEngine.php` - Main orchestrator
- `/app/Engine/Graph/GraphCompiler.php` - Parse workflow definitions
- `/app/Engine/Execution/ExecutionScheduler.php` - Node scheduling
- `/app/Engine/RunContext.php` - Execution state

**API Endpoints:**
- `/routes/api.php` - All REST API routes

**Models:**
- `/app/Models/Workflow.php` - Workflow definition
- `/app/Models/Execution.php` - Workflow run
- `/app/Models/Workspace.php` - Multi-tenant workspace
- `/app/Models/User.php` - User accounts

**Services:**
- `/app/Services/WorkflowService.php` - Workflow CRUD
- `/app/Services/ExecutionService.php` - Execution management
- `/app/Services/WebhookService.php` - Webhook handling

**Jobs:**
- `/app/Jobs/ExecuteWorkflowJob.php` - Main execution job
- `/app/Jobs/ResumeWorkflowJob.php` - Resume suspended workflows

**Nodes:**
- `/app/Engine/Nodes/Apps/` - App integration nodes
- `/app/Engine/Nodes/Flow/` - Flow control nodes
- `/app/Engine/Nodes/Core/` - Core system nodes

---

## 🔑 Key Concepts

- **Workspace** - Multi-tenant container for all resources
- **Workflow** - Saved automation blueprint (nodes + connections)
- **Execution** - Single run of a workflow
- **Node** - Individual step in workflow
- **Trigger** - How workflow starts (webhook, schedule, manual)
- **Suspension** - Workflow pauses waiting for external event
- **Checkpoint** - Saved state for crash recovery

---

## 🌐 API Base URL

**Local Development:**
```
http://localhost/api/v1
```

**Production:**
```
https://api.yourdomain.com/api/v1
```

---

## 🔐 Authentication

All API requests require authentication:

```bash
Authorization: Bearer {access_token}
```

Get token via `/api/v1/auth/login` endpoint.

---

## 🆘 Getting Help

### Documentation Issues
1. Check if there's a related doc in this index
2. Search the docs folder for keywords
3. Check code comments in relevant files
4. Consult the team

### Code Issues
1. Check [Troubleshooting Guide](./guides/troubleshooting.md)
2. Review logs (`storage/logs/`)
3. Check Horizon dashboard (`/horizon`)
4. Use Tinker for debugging (`php artisan tinker`)

### Quick Diagnostics
```bash
# Run health check
php artisan app:health-check

# Check all services
sudo systemctl status nginx php8.3-fpm postgresql redis

# View recent errors
tail -n 50 storage/logs/laravel.log | grep ERROR
```

---

## 🔄 Keeping Documentation Updated

When making changes to the codebase:

1. **New Feature** → Update [Feature Status](./planning/feature-status.md)
2. **API Change** → Update [API Reference](./reference/api.md) & relevant frontend modules
3. **Architecture Change** → Update [Architecture](./core/02-architecture.md)
4. **New Node** → Update [Node Reference](./reference/nodes.md) & document in code
5. **Config Change** → Update [Developer Handbook](./core/04-developer-handbook.md)

---

## ✅ New Developer Onboarding Checklist

**Day 1:**
- [ ] Read [Project Overview](./core/01-project-overview.md)
- [ ] Set up development environment ([Developer Handbook](./core/04-developer-handbook.md))
- [ ] Run the app and explore UI
- [ ] Create a simple workflow manually

**Week 1:**
- [ ] Read [Architecture](./core/02-architecture.md)
- [ ] Read [Workflow Engine](./core/03-workflow-engine.md)
- [ ] Make first code contribution (small bug fix)
- [ ] Write tests for your changes
- [ ] Review API endpoints in [Postman](./Agent1o1-API.postman_collection.json)

**Month 1:**
- [ ] Understand all core concepts
- [ ] Read relevant frontend integration modules
- [ ] Implement a new node type
- [ ] Fix a production bug
- [ ] Help another developer with code review

---

## 🚀 You're All Set!

**Everything you need to understand and develop LinkFlow is here.**

- **New to the project?** Start with [Project Overview](./core/01-project-overview.md)
- **Need to deploy?** See [Deployment Guide](./guides/deployment.md)
- **Building frontend?** Check [Frontend Integration](./frontend/README.md)
- **Debugging issues?** Use [Troubleshooting Guide](./guides/troubleshooting.md)
- **API reference?** See [API Reference](./reference/api.md)

---

*Last Updated: December 2024*
*Documentation Version: 2.0 (Reorganized)*
