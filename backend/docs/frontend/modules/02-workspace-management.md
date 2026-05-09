# 🏢 Module 2: Workspace Management

**Switch between workspaces, manage settings, view activity**

**APIs:** `/api/v1/workspaces/*`  
**Components:** WorkspaceSwitcher, WorkspaceSettings, WorkspaceList

---

## 🔗 API Endpoints

### 1. List Workspaces
```http
GET /api/v1/workspaces
Authorization: Bearer {token}

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "name": "My Workspace",
      "slug": "my-workspace",
      "role": "owner",
      "created_at": "2024-01-01T00:00:00.000000Z"
    }
  ]
}
```

### 2. Create Workspace
```http
POST /api/v1/workspaces
Content-Type: application/json

{
  "name": "New Workspace",
  "slug": "new-workspace"
}

Response (201):
{
  "data": {
    "id": "uuid",
    "name": "New Workspace",
    "slug": "new-workspace",
    "role": "owner"
  }
}
```

### 3. Get Workspace Details
```http
GET /api/v1/workspaces/{id}

Response (200):
{
  "data": {
    "id": "uuid",
    "name": "My Workspace",
    "slug": "my-workspace",
    "settings": {
      "timezone": "UTC",
      "default_workflow_timeout": 3600
    },
    "members_count": 5,
    "workflows_count": 12,
    "role": "owner"
  }
}
```

### 4. Update Workspace
```http
PUT /api/v1/workspaces/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "settings": {
    "timezone": "America/New_York"
  }
}
```

### 5. Delete Workspace
```http
DELETE /api/v1/workspaces/{id}

Response (204): No Content
```

---

## 🗄️ State Management

```javascript
// src/stores/workspaceStore.js
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export const useWorkspaceStore = create(
  persist(
    (set, get) => ({
      // State
      workspaces: [],
      currentWorkspace: null,
      
      // Actions
      setWorkspaces: (workspaces) => set({ workspaces }),
      
      setCurrentWorkspace: (workspace) => set({ currentWorkspace: workspace }),
      
      switchWorkspace: (workspaceId) => {
        const workspace = get().workspaces.find(w => w.id === workspaceId);
        if (workspace) {
          set({ currentWorkspace: workspace });
        }
      },
      
      addWorkspace: (workspace) => set((state) => ({
        workspaces: [...state.workspaces, workspace],
      })),
      
      updateWorkspace: (workspaceId, updates) => set((state) => ({
        workspaces: state.workspaces.map(w =>
          w.id === workspaceId ? { ...w, ...updates } : w
        ),
        currentWorkspace: state.currentWorkspace?.id === workspaceId
          ? { ...state.currentWorkspace, ...updates }
          : state.currentWorkspace,
      })),
      
      removeWorkspace: (workspaceId) => set((state) => ({
        workspaces: state.workspaces.filter(w => w.id !== workspaceId),
        currentWorkspace: state.currentWorkspace?.id === workspaceId
          ? state.workspaces[0]
          : state.currentWorkspace,
      })),
    }),
    {
      name: 'workspace-storage',
      partialize: (state) => ({
        currentWorkspace: state.currentWorkspace,
      }),
    }
  )
);
```

---

## 🎨 Components

### 1. Workspace Switcher
```jsx
// src/components/workspace/WorkspaceSwitcher.jsx
import { Fragment } from 'react';
import { Menu, Transition } from '@headlessui/react';
import { ChevronDownIcon } from '@heroicons/react/20/solid';
import { useWorkspaceStore } from '../../stores/workspaceStore';
import { useQuery } from '@tanstack/react-query';
import { workspacesApi } from '../../api/workspaces';

export default function WorkspaceSwitcher() {
  const { currentWorkspace, workspaces, setWorkspaces, switchWorkspace } = useWorkspaceStore();
  
  const { data } = useQuery({
    queryKey: ['workspaces'],
    queryFn: workspacesApi.list,
    onSuccess: (data) => {
      setWorkspaces(data.data);
      if (!currentWorkspace && data.data.length > 0) {
        switchWorkspace(data.data[0].id);
      }
    },
  });

  return (
    <Menu as="div" className="relative">
      <Menu.Button className="flex items-center gap-2 px-3 py-2 bg-white border rounded-lg hover:bg-gray-50">
        <span className="font-medium">{currentWorkspace?.name || 'Select Workspace'}</span>
        <ChevronDownIcon className="w-5 h-5 text-gray-400" />
      </Menu.Button>

      <Transition
        as={Fragment}
        enter="transition ease-out duration-100"
        enterFrom="transform opacity-0 scale-95"
        enterTo="transform opacity-100 scale-100"
        leave="transition ease-in duration-75"
        leaveFrom="transform opacity-100 scale-100"
        leaveTo="transform opacity-0 scale-95"
      >
        <Menu.Items className="absolute left-0 mt-2 w-64 bg-white border rounded-lg shadow-lg z-50">
          <div className="p-2">
            <div className="text-xs text-gray-500 px-3 py-2">YOUR WORKSPACES</div>
            
            {workspaces.map((workspace) => (
              <Menu.Item key={workspace.id}>
                {({ active }) => (
                  <button
                    onClick={() => switchWorkspace(workspace.id)}
                    className={`w-full text-left px-3 py-2 rounded ${
                      active ? 'bg-gray-100' : ''
                    } ${
                      currentWorkspace?.id === workspace.id ? 'bg-blue-50 text-blue-600' : ''
                    }`}
                  >
                    <div className="font-medium">{workspace.name}</div>
                    <div className="text-xs text-gray-500">{workspace.role}</div>
                  </button>
                )}
              </Menu.Item>
            ))}
            
            <div className="border-t my-2" />
            
            <Menu.Item>
              {({ active }) => (
                <button
                  className={`w-full text-left px-3 py-2 rounded text-sm ${
                    active ? 'bg-gray-100' : ''
                  }`}
                >
                  + Create Workspace
                </button>
              )}
            </Menu.Item>
          </div>
        </Menu.Items>
      </Transition>
    </Menu>
  );
}
```

### 2. Create Workspace Modal
```jsx
// src/components/workspace/CreateWorkspaceModal.jsx
import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { workspacesApi } from '../../api/workspaces';
import { useWorkspaceStore } from '../../stores/workspaceStore';

export default function CreateWorkspaceModal({ isOpen, onClose }) {
  const [name, setName] = useState('');
  const [error, setError] = useState('');
  const queryClient = useQueryClient();
  const addWorkspace = useWorkspaceStore((state) => state.addWorkspace);

  const createMutation = useMutation({
    mutationFn: workspacesApi.create,
    onSuccess: (data) => {
      addWorkspace(data.data);
      queryClient.invalidateQueries(['workspaces']);
      onClose();
      setName('');
    },
    onError: (err) => {
      setError(err.response?.data?.message || 'Failed to create workspace');
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    setError('');
    createMutation.mutate({ name });
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 className="text-xl font-bold mb-4">Create Workspace</h2>
        
        <form onSubmit={handleSubmit}>
          {error && (
            <div className="bg-red-50 text-red-600 p-3 rounded mb-4">
              {error}
            </div>
          )}
          
          <div className="mb-4">
            <label className="block text-sm font-medium mb-2">
              Workspace Name
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="My Workspace"
              className="w-full px-3 py-2 border rounded"
              required
            />
          </div>

          <div className="flex gap-3">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 px-4 py-2 border rounded hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={createMutation.isPending}
              className="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
              {createMutation.isPending ? 'Creating...' : 'Create'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
```

### 3. Workspace Settings Page
```jsx
// src/pages/WorkspaceSettings.jsx
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useWorkspaceStore } from '../stores/workspaceStore';
import { workspacesApi } from '../api/workspaces';

export default function WorkspaceSettings() {
  const currentWorkspace = useWorkspaceStore((state) => state.currentWorkspace);
  const updateWorkspace = useWorkspaceStore((state) => state.updateWorkspace);
  const queryClient = useQueryClient();

  const { data: workspace } = useQuery({
    queryKey: ['workspace', currentWorkspace?.id],
    queryFn: () => workspacesApi.get(currentWorkspace.id),
    enabled: !!currentWorkspace,
  });

  const [formData, setFormData] = useState({
    name: workspace?.data?.name || '',
    timezone: workspace?.data?.settings?.timezone || 'UTC',
  });

  const updateMutation = useMutation({
    mutationFn: (data) => workspacesApi.update(currentWorkspace.id, data),
    onSuccess: (data) => {
      updateWorkspace(currentWorkspace.id, data.data);
      queryClient.invalidateQueries(['workspace', currentWorkspace.id]);
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    updateMutation.mutate(formData);
  };

  return (
    <div className="max-w-2xl mx-auto p-6">
      <h1 className="text-2xl font-bold mb-6">Workspace Settings</h1>

      <form onSubmit={handleSubmit} className="space-y-6">
        <div>
          <label className="block text-sm font-medium mb-2">
            Workspace Name
          </label>
          <input
            type="text"
            value={formData.name}
            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
            className="w-full px-3 py-2 border rounded"
          />
        </div>

        <div>
          <label className="block text-sm font-medium mb-2">
            Timezone
          </label>
          <select
            value={formData.timezone}
            onChange={(e) => setFormData({ ...formData, timezone: e.target.value })}
            className="w-full px-3 py-2 border rounded"
          >
            <option value="UTC">UTC</option>
            <option value="America/New_York">Eastern Time</option>
            <option value="America/Los_Angeles">Pacific Time</option>
            <option value="Europe/London">London</option>
          </select>
        </div>

        <button
          type="submit"
          disabled={updateMutation.isPending}
          className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
        >
          {updateMutation.isPending ? 'Saving...' : 'Save Changes'}
        </button>

        {updateMutation.isSuccess && (
          <p className="text-green-600">Settings saved!</p>
        )}
      </form>
    </div>
  );
}
```

---

## 📁 API Module

```javascript
// src/api/workspaces.js
import apiClient from './client';

export const workspacesApi = {
  list: () =>
    apiClient.get('/workspaces').then(res => res.data),
  
  get: (workspaceId) =>
    apiClient.get(`/workspaces/${workspaceId}`).then(res => res.data),
  
  create: (data) =>
    apiClient.post('/workspaces', data).then(res => res.data),
  
  update: (workspaceId, data) =>
    apiClient.put(`/workspaces/${workspaceId}`, data).then(res => res.data),
  
  delete: (workspaceId) =>
    apiClient.delete(`/workspaces/${workspaceId}`).then(res => res.data),
};
```

---

## 🔄 Integration with Auth

```javascript
// After login, fetch and set workspace
import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useWorkspaceStore } from './stores/workspaceStore';
import { workspacesApi } from './api/workspaces';

export function useInitializeWorkspace() {
  const { setWorkspaces, switchWorkspace, currentWorkspace } = useWorkspaceStore();
  
  const { data } = useQuery({
    queryKey: ['workspaces'],
    queryFn: workspacesApi.list,
  });

  useEffect(() => {
    if (data?.data) {
      setWorkspaces(data.data);
      
      // Set first workspace as current if none selected
      if (!currentWorkspace && data.data.length > 0) {
        switchWorkspace(data.data[0].id);
      }
    }
  }, [data, currentWorkspace, setWorkspaces, switchWorkspace]);
}

// Use in App.jsx
function App() {
  useInitializeWorkspace();
  
  return <YourApp />;
}
```

---

## ✅ Checklist

- [ ] Create workspace store
- [ ] Implement WorkspaceSwitcher component
- [ ] Add workspace creation modal
- [ ] Build workspace settings page
- [ ] Integrate with auth flow
- [ ] Handle workspace switching
- [ ] Persist current workspace
- [ ] Add workspace delete confirmation
- [ ] Show workspace stats
- [ ] Add workspace invitations (Team module)

---

**Next:** [Workflow Management →](./04-workflow-management.md)
