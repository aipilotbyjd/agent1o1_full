# 🎨 React Frontend Integration Plan

**Platform:** Agent1o1 Workflow Automation  
**Frontend:** React + Vite + Tailwind CSS  
**Backend:** Laravel 12 REST API  
**Date:** 2026-04-04

---

## 📚 Documentation Structure

This guide is split into **15 separate modules** for easier development and maintenance:

### Core Features
1. **[Authentication & User Profile](./frontend-integration/01-authentication.md)** - Login, register, profile management
2. **[Workspace Management](./frontend-integration/02-workspace-management.md)** - Workspace CRUD, switching, settings
3. **[Workflow Editor](./frontend-integration/03-workflow-editor.md)** - Visual canvas, node management, connections
4. **[Workflow Management](./frontend-integration/04-workflow-management.md)** - List, create, edit, delete workflows
5. **[Execution Dashboard](./frontend-integration/05-execution-dashboard.md)** - Monitor and manage workflow runs

### Data & Integrations
6. **[Credentials Management](./frontend-integration/06-credentials.md)** - API keys, OAuth, credential CRUD
7. **[Variables & Tags](./frontend-integration/07-variables-tags.md)** - Variable storage, workflow tagging
8. **[Webhooks](./frontend-integration/08-webhooks.md)** - Webhook management, testing

### Content & Collaboration
9. **[Templates Marketplace](./frontend-integration/09-templates.md)** - Browse, use, share templates
10. **[Team Management](./frontend-integration/10-team.md)** - Invite members, manage roles
11. **[Notifications](./frontend-integration/11-notifications.md)** - In-app notifications, preferences

### Admin & Monitoring
12. **[Settings](./frontend-integration/12-settings.md)** - User settings, workspace settings, billing
13. **[Analytics & Monitoring](./frontend-integration/13-analytics.md)** - Dashboard, metrics, logs

### Advanced Features
14. **[AI Agents System](./frontend-integration/14-agents.md)** - Create and manage AI conversational agents
15. **[Advanced Workflow Features](./frontend-integration/15-advanced-workflow.md)** - Versioning, sharing, import/export

---

## 🏗️ Tech Stack

### Frontend
```json
{
  "framework": "React 18",
  "build": "Vite",
  "styling": "Tailwind CSS 4",
  "state": "React Query + Zustand (recommended)",
  "routing": "React Router v6",
  "workflow-canvas": "React Flow or similar",
  "forms": "React Hook Form",
  "http": "Axios"
}
```

### Backend API
```
Base URL: https://your-domain.com/api/v1
Auth: Bearer Token
Format: JSON
```

---

## 🔑 Core Concepts

### 1. Authentication Flow
```
Register/Login → Get access_token → Store in localStorage
→ Include in all API requests as Bearer token
→ Refresh token when expired
```

### 2. Workspace Context
```
Most APIs require workspace_id in the URL:
/api/v1/workspaces/{workspace_id}/...

Store current workspace in global state
Switch workspace = update state + refetch data
```

### 3. State Management
```javascript
// Recommended structure
Global State (Zustand):
- auth (user, token)
- workspace (current workspace)
- theme (dark mode)

Server State (React Query):
- workflows
- executions
- credentials
- etc.
```

---

## 📦 Recommended Package Setup

### Install Dependencies
```bash
npm install react-query axios zustand
npm install react-router-dom react-hook-form
npm install @tanstack/react-query
npm install reactflow  # for workflow canvas
npm install date-fns    # for date formatting
npm install recharts    # for charts
```

### Project Structure
```
src/
├── api/
│   ├── client.js           # Axios instance with auth
│   ├── auth.js             # Auth API calls
│   ├── workflows.js        # Workflow API calls
│   ├── executions.js       # Execution API calls
│   └── ...
├── components/
│   ├── workflow-editor/    # Workflow canvas components
│   ├── common/             # Shared components
│   └── ...
├── pages/
│   ├── Dashboard.jsx
│   ├── WorkflowEditor.jsx
│   ├── Executions.jsx
│   └── ...
├── stores/
│   ├── authStore.js        # Auth state
│   ├── workspaceStore.js   # Workspace state
│   └── ...
├── hooks/
│   ├── useAuth.js
│   ├── useWorkflows.js
│   └── ...
└── utils/
    ├── api.js              # API helpers
    └── constants.js
```

---

## 🚀 Quick Start Implementation Order

### Phase 1: Foundation (Week 1)
1. Setup project structure
2. Configure Axios + React Query
3. Implement Authentication (Module 1)
4. Implement Workspace Management (Module 2)

### Phase 2: Core Features (Week 2-3)
5. Workflow List & Management (Module 4)
6. Workflow Editor Canvas (Module 3)
7. Execution Dashboard (Module 5)

### Phase 3: Integrations (Week 4)
8. Credentials (Module 6)
9. Variables & Tags (Module 7)
10. Webhooks (Module 8)

### Phase 4: Collaboration (Week 5)
11. Templates (Module 9)
12. Team Management (Module 10)
13. Notifications (Module 11)

### Phase 5: Admin (Week 6)
14. Settings (Module 12)
15. Analytics (Module 13)

---

## 🔗 API Overview

### Authentication APIs
```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/refresh
POST   /api/v1/auth/logout
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
```

### Workspace APIs
```
GET    /api/v1/workspaces
POST   /api/v1/workspaces
GET    /api/v1/workspaces/{id}
PUT    /api/v1/workspaces/{id}
DELETE /api/v1/workspaces/{id}
```

### Workflow APIs
```
GET    /api/v1/workspaces/{workspace}/workflows
POST   /api/v1/workspaces/{workspace}/workflows
GET    /api/v1/workspaces/{workspace}/workflows/{id}
PUT    /api/v1/workspaces/{workspace}/workflows/{id}
DELETE /api/v1/workspaces/{workspace}/workflows/{id}
POST   /api/v1/workspaces/{workspace}/workflows/{id}/execute
POST   /api/v1/workspaces/{workspace}/workflows/{id}/activate
POST   /api/v1/workspaces/{workspace}/workflows/{id}/deactivate
```

### Execution APIs
```
GET    /api/v1/workspaces/{workspace}/executions
GET    /api/v1/workspaces/{workspace}/executions/{id}
POST   /api/v1/workspaces/{workspace}/executions/{id}/cancel
POST   /api/v1/workspaces/{workspace}/executions/{id}/retry
GET    /api/v1/workspaces/{workspace}/executions/{id}/logs
```

### Catalog APIs (Node Library)
```
GET    /api/v1/workspaces/{workspace}/catalog/nodes
GET    /api/v1/workspaces/{workspace}/catalog/nodes/{type}
GET    /api/v1/workspaces/{workspace}/catalog/credential-types
```

---

## 📝 Code Examples

### Axios Client Setup
```javascript
// src/api/client.js
import axios from 'axios';

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Add auth token to requests
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle token refresh
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Try to refresh token
      try {
        const refreshToken = localStorage.getItem('refresh_token');
        const response = await axios.post('/api/v1/auth/refresh', {
          refresh_token: refreshToken,
        });
        localStorage.setItem('access_token', response.data.access_token);
        // Retry original request
        return apiClient(error.config);
      } catch (refreshError) {
        // Redirect to login
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

export default apiClient;
```

### React Query Setup
```javascript
// src/main.jsx
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: 1,
      staleTime: 5 * 60 * 1000, // 5 minutes
    },
  },
});

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <YourApp />
    </QueryClientProvider>
  );
}
```

### Custom Hook Example
```javascript
// src/hooks/useWorkflows.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { workflowsApi } from '../api/workflows';
import { useWorkspaceStore } from '../stores/workspaceStore';

export function useWorkflows() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);
  
  return useQuery({
    queryKey: ['workflows', workspaceId],
    queryFn: () => workflowsApi.list(workspaceId),
    enabled: !!workspaceId,
  });
}

export function useCreateWorkflow() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);
  
  return useMutation({
    mutationFn: (data) => workflowsApi.create(workspaceId, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['workflows', workspaceId]);
    },
  });
}
```

---

## 🎯 Next Steps

1. **Read Module 1:** [Authentication & User Profile](./frontend-integration/01-authentication.md)
2. **Setup project structure** as outlined above
3. **Configure Axios and React Query**
4. **Follow the implementation order** (Phase 1 → Phase 5)

---

## 📖 Module Quick Links

1. [Authentication & User Profile](./frontend-integration/01-authentication.md)
2. [Workspace Management](./frontend-integration/02-workspace-management.md)
3. [Workflow Editor](./frontend-integration/03-workflow-editor.md)
4. [Workflow Management](./frontend-integration/04-workflow-management.md)
5. [Execution Dashboard](./frontend-integration/05-execution-dashboard.md)
6. [Credentials Management](./frontend-integration/06-credentials.md)
7. [Variables & Tags](./frontend-integration/07-variables-tags.md)
8. [Webhooks](./frontend-integration/08-webhooks.md)
9. [Templates Marketplace](./frontend-integration/09-templates.md)
10. [Team Management](./frontend-integration/10-team.md)
11. [Notifications](./frontend-integration/11-notifications.md)
12. [Settings](./frontend-integration/12-settings.md)
13. [Analytics & Monitoring](./frontend-integration/13-analytics.md)
14. [AI Agents System](./frontend-integration/14-agents.md)
15. [Advanced Workflow Features](./frontend-integration/15-advanced-workflow.md)

---

**Let's build an amazing workflow automation platform! 🚀**
