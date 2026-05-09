# 📋 Module 4: Workflow Management

**List, create, update, delete workflows**

**APIs:** `/api/v1/workspaces/{workspace}/workflows/*`

---

## 🔗 API Endpoints

### 1. List Workflows
```http
GET /api/v1/workspaces/{workspace_id}/workflows
Query Params:
  - page: 1
  - per_page: 20
  - search: "query"
  - status: active|inactive
  - sort: name|created_at|updated_at
  - order: asc|desc

Response:
{
  "data": [
    {
      "id": "uuid",
      "name": "My Workflow",
      "description": "...",
      "status": "active",
      "nodes_count": 5,
      "last_execution_at": "2024-01-01T00:00:00Z",
      "created_at": "2024-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 50
  }
}
```

### 2. Create Workflow
```http
POST /api/v1/workspaces/{workspace_id}/workflows

{
  "name": "New Workflow",
  "description": "Description",
  "nodes": [],
  "edges": []
}
```

### 3. Duplicate Workflow
```http
POST /api/v1/workspaces/{workspace_id}/workflows/{id}/duplicate

Response: New workflow object
```

### 4. Activate/Deactivate
```http
POST /api/v1/workspaces/{workspace_id}/workflows/{id}/activate
POST /api/v1/workspaces/{workspace_id}/workflows/{id}/deactivate
```

---

## 🎨 Components

### Workflow List
```jsx
import { useQuery } from '@tanstack/react-query';
import { useWorkspaceStore } from '../stores/workspaceStore';
import { workflowsApi } from '../api/workflows';
import { Link } from 'react-router-dom';

export default function WorkflowList() {
  const workspaceId = useWorkspaceStore((s) => s.currentWorkspace?.id);
  const [search, setSearch] = useState('');
  
  const { data, isLoading } = useQuery({
    queryKey: ['workflows', workspaceId, search],
    queryFn: () => workflowsApi.list(workspaceId, { search }),
    enabled: !!workspaceId,
  });

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Workflows</h1>
        <Link
          to="/workflows/new"
          className="px-4 py-2 bg-blue-600 text-white rounded"
        >
          Create Workflow
        </Link>
      </div>

      <input
        type="text"
        placeholder="Search workflows..."
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        className="w-full px-4 py-2 border rounded mb-6"
      />

      {isLoading ? (
        <div>Loading...</div>
      ) : (
        <div className="grid gap-4">
          {data?.data?.map((workflow) => (
            <WorkflowCard key={workflow.id} workflow={workflow} />
          ))}
        </div>
      )}
    </div>
  );
}
```

### Workflow Card
```jsx
function WorkflowCard({ workflow }) {
  return (
    <Link
      to={`/workflows/${workflow.id}`}
      className="block p-4 bg-white border rounded-lg hover:shadow-md transition"
    >
      <div className="flex justify-between items-start">
        <div>
          <h3 className="text-lg font-semibold">{workflow.name}</h3>
          <p className="text-sm text-gray-500">{workflow.description}</p>
          <div className="flex gap-4 mt-2 text-sm text-gray-600">
            <span>{workflow.nodes_count} nodes</span>
            <span>Last run: {workflow.last_execution_at || 'Never'}</span>
          </div>
        </div>
        <span
          className={`px-2 py-1 text-xs rounded ${
            workflow.status === 'active'
              ? 'bg-green-100 text-green-800'
              : 'bg-gray-100 text-gray-800'
          }`}
        >
          {workflow.status}
        </span>
      </div>
    </Link>
  );
}
```

---

## ✅ Features
- [ ] Workflow list with pagination
- [ ] Search and filter
- [ ] Create new workflow
- [ ] Duplicate workflow
- [ ] Delete workflow
- [ ] Activate/deactivate toggle
- [ ] Bulk actions
- [ ] Sort by various fields
