# 🧭 Documentation Navigation Guide

**Quick reference for navigating the LinkFlow documentation**

---

## 📁 Folder Structure

```
/app/docs/
│
├── 📄 README.md                      ← START HERE (Main index)
│
├── 📘 core/                          Essential guides
│   ├── 01-project-overview.md        What is LinkFlow?
│   ├── 02-architecture.md            Technical deep dive
│   ├── 03-workflow-engine.md         Engine internals
│   └── 04-developer-handbook.md      Development guide
│
├── 🔧 guides/                        Practical how-to guides
│   ├── deployment.md                 Complete deployment
│   ├── security.md                   Security best practices
│   ├── testing.md                    Testing strategies
│   ├── troubleshooting.md            Issue resolution
│   └── webhooks.md                   Webhook implementation
│
├── 📚 reference/                     Technical reference
│   ├── api.md                        REST API docs
│   ├── nodes.md                      Workflow nodes
│   ├── database-schema.md            Schema reference
│   └── postman.md                    API testing
│
├── 🎨 frontend/                      Frontend integration
│   ├── README.md                     Main integration guide
│   └── modules/                      15 detailed modules
│       ├── 01-authentication.md
│       ├── 02-workspace-management.md
│       ├── 03-workflow-editor.md
│       └── ... (12 more modules)
│
├── 🚀 deployment/                    Platform-specific deployment
│   ├── README.md                     Deployment overview
│   ├── 01-architecture.md            Infrastructure design
│   ├── 02-docker-configuration.md    Docker setup
│   ├── 03-hetzner-deployment.md      Hetzner VPS
│   ├── 04-azure-deployment.md        Azure VM
│   ├── 05-aws-deployment.md          AWS EC2
│   └── ... (5 more guides)
│
├── 📋 planning/                      Project planning & roadmap
│   ├── roadmap.md                    Future features
│   ├── feature-status.md             Implementation status
│   ├── pricing-and-roles.md          Business model
│   ├── s3-log-archiving-plan.md      S3 cold storage design
│   └── ... (6 more docs)
│
└── 📦 archive/                       Historical documents
    └── ... (completed milestones)
```

---

## 🎯 Common Navigation Patterns

### "I'm new to the project"
```
README.md
  → core/01-project-overview.md
    → core/02-architecture.md
      → core/04-developer-handbook.md
```

### "I need to deploy"
```
README.md
  → guides/deployment.md
    → deployment/03-hetzner-deployment.md (or 04-azure, 05-aws)
      → guides/security.md
        → deployment/08-monitoring.md
```

### "I'm building the frontend"
```
README.md
  → frontend/README.md
    → frontend/modules/01-authentication.md
      → frontend/modules/02-workspace-management.md
        → ... (continue through modules)
```

### "I'm debugging an issue"
```
README.md
  → guides/troubleshooting.md
    → (Find your issue category)
      → reference/api.md (if API issue)
        → core/03-workflow-engine.md (if workflow issue)
```

### "I need API reference"
```
README.md
  → reference/api.md
    → reference/nodes.md
      → reference/database-schema.md
```

### "I'm writing tests"
```
README.md
  → guides/testing.md
    → core/04-developer-handbook.md
      → reference/api.md
```

---

## 📖 Reading Order by Topic

### Full Stack Development (Complete Path)
1. **Week 1:** core/01-project-overview.md → core/02-architecture.md
2. **Week 2:** core/04-developer-handbook.md → guides/testing.md
3. **Week 3:** frontend/README.md → frontend/modules (all 15)
4. **Week 4:** guides/deployment.md → guides/security.md

### Backend Only
1. core/01-project-overview.md
2. core/02-architecture.md
3. core/03-workflow-engine.md
4. core/04-developer-handbook.md
5. reference/api.md
6. reference/database-schema.md
7. guides/testing.md

### Frontend Only
1. core/01-project-overview.md (overview)
2. frontend/README.md
3. frontend/modules/01-authentication.md
4. frontend/modules/02-workspace-management.md
5. ... (continue through all 15 modules)
6. reference/api.md (for API details)

### DevOps Only
1. core/01-project-overview.md (understand the app)
2. core/02-architecture.md (infrastructure needs)
3. guides/deployment.md (main deployment guide)
4. deployment/01-architecture.md → deployment/10-cicd.md (all)
5. guides/security.md
6. guides/troubleshooting.md

---

## 🔍 Finding What You Need

### By File Type
- **Markdown files (*.md)** - All documentation
- **JSON files** - Postman collections for API testing

### By Role
See **README.md** → "Quick Start by Role" section

### By Task
- **Setup dev environment** → core/04-developer-handbook.md
- **Add new API endpoint** → core/04-developer-handbook.md → "Adding API Endpoint"
- **Create new workflow node** → core/04-developer-handbook.md → "Creating Nodes"
- **Deploy to production** → guides/deployment.md
- **Fix security issue** → guides/security.md
- **Debug workflow execution** → guides/troubleshooting.md → "Workflow Execution Issues"
- **Write tests** → guides/testing.md
- **Integrate frontend** → frontend/README.md

### By Keyword
Use your editor's search or:
```bash
cd /app/docs
grep -r "keyword" --include="*.md"
```

---

## 🚀 Quick Access Links

**Most Frequently Used:**
- [Main Index](./README.md)
- [Project Overview](./core/01-project-overview.md)
- [Developer Handbook](./core/04-developer-handbook.md)
- [API Reference](./reference/api.md)
- [Troubleshooting](./guides/troubleshooting.md)

**Getting Started:**
- [Architecture](./core/02-architecture.md)
- [Workflow Engine](./core/03-workflow-engine.md)
- [Frontend Integration](./frontend/README.md)

**Operations:**
- [Deployment Guide](./guides/deployment.md)
- [Security Guide](./guides/security.md)
- [Monitoring](./deployment/08-monitoring.md)

---

## 📱 Mobile-Friendly Navigation

For mobile devices or smaller screens, use the table of contents at the top of each document.

All documents have:
- ✅ Clear headings (H1, H2, H3)
- ✅ Table of contents
- ✅ Anchor links for quick navigation
- ✅ "Back to top" links (in longer docs)

---

## 💡 Tips

1. **Bookmark README.md** - It's your home base
2. **Use browser's Find (Ctrl+F)** - Search within current doc
3. **Open multiple tabs** - Keep reference docs open while coding
4. **Follow the links** - Related docs are cross-linked
5. **Check the archive** - Old decisions and context are preserved

---

**Lost? Start here: [README.md](./README.md)** 🏠

*Last Updated: December 2024*
