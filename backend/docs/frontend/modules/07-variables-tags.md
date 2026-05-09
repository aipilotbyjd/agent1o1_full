# 📦 Module 7: Variables & Tags

**Store workspace-wide variables and organize workflows with tags**

**APIs:** `/api/v1/workspaces/{workspace}/variables/*`, `/api/v1/workspaces/{workspace}/tags/*`  
**Components:** VariablesList, VariableEditor, TagManager, TagFilter

---

## 🔗 API Endpoints

### Variables Management

#### 1. List Variables
```http
GET /api/v1/workspaces/{workspace}/variables
Authorization: Bearer {token}

Query Parameters:
- search (optional): Search by key or description

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "key": "api_base_url",
      "value": "https://api.example.com",
      "type": "string",
      "is_secret": false,
      "description": "Base URL for external API",
      "created_at": "2024-01-01T00:00:00Z"
    },
    {
      "id": "uuid",
      "key": "db_password",
      "value": "***", // Masked if is_secret=true
      "type": "secret",
      "is_secret": true,
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### 2. Create Variable
```http
POST /api/v1/workspaces/{workspace}/variables
Content-Type: application/json

{
  "key": "max_retries",
  "value": "3",
  "type": "number",
  "is_secret": false,
  "description": "Maximum retry attempts for API calls"
}

Response (201):
{
  "data": {
    "id": "uuid",
    "key": "max_retries",
    "value": "3",
    "type": "number"
  }
}
```

#### 3. Get Variable
```http
GET /api/v1/workspaces/{workspace}/variables/{id}

Response (200):
{
  "data": {
    "id": "uuid",
    "key": "api_key",
    "value": "sk-123..." // Full value if user has permission
  }
}
```

#### 4. Update Variable
```http
PUT /api/v1/workspaces/{workspace}/variables/{id}
Content-Type: application/json

{
  "value": "5",
  "description": "Updated max retries to 5"
}
```

#### 5. Delete Variable
```http
DELETE /api/v1/workspaces/{workspace}/variables/{id}

Response (204): No Content
```

### Tags Management

#### 6. List Tags
```http
GET /api/v1/workspaces/{workspace}/tags

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "name": "Production",
      "color": "#10b981",
      "workflows_count": 5,
      "created_at": "2024-01-01T00:00:00Z"
    },
    {
      "id": "uuid",
      "name": "Testing",
      "color": "#f59e0b",
      "workflows_count": 3
    }
  ]
}
```

#### 7. Create Tag
```http
POST /api/v1/workspaces/{workspace}/tags
Content-Type: application/json

{
  "name": "Automation",
  "color": "#3b82f6"
}

Response (201):
{
  "data": {
    "id": "uuid",
    "name": "Automation",
    "color": "#3b82f6"
  }
}
```

#### 8. Get Tag Details
```http
GET /api/v1/workspaces/{workspace}/tags/{id}

Response (200):
{
  "data": {
    "id": "uuid",
    "name": "Production",
    "color": "#10b981",
    "workflows": [
      {
        "id": "uuid",
        "name": "User Onboarding",
        "is_active": true
      }
    ]
  }
}
```

#### 9. Update Tag
```http
PUT /api/v1/workspaces/{workspace}/tags/{id}
Content-Type: application/json

{
  "name": "Production v2",
  "color": "#059669"
}
```

#### 10. Delete Tag
```http
DELETE /api/v1/workspaces/{workspace}/tags/{id}

Response (204): No Content
```

#### 11. Attach Workflows to Tag
```http
POST /api/v1/workspaces/{workspace}/tags/{id}/workflows
Content-Type: application/json

{
  "workflow_ids": ["uuid-1", "uuid-2", "uuid-3"]
}

Response (200):
{
  "message": "Workflows attached successfully"
}
```

#### 12. Detach Workflows from Tag
```http
DELETE /api/v1/workspaces/{workspace}/tags/{id}/workflows
Content-Type: application/json

{
  "workflow_ids": ["uuid-1"]
}

Response (200):
{
  "message": "Workflows detached successfully"
}
```

---

## 🗄️ State Management

### API Client
```javascript
// src/api/variables.js
import apiClient from './client';

export const variablesApi = {
  list: (workspaceId, params = {}) => 
    apiClient.get(`/workspaces/${workspaceId}/variables`, { params }),
  
  get: (workspaceId, variableId) => 
    apiClient.get(`/workspaces/${workspaceId}/variables/${variableId}`),
  
  create: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/variables`, data),
  
  update: (workspaceId, variableId, data) => 
    apiClient.put(`/workspaces/${workspaceId}/variables/${variableId}`, data),
  
  delete: (workspaceId, variableId) => 
    apiClient.delete(`/workspaces/${workspaceId}/variables/${variableId}`),
};

export const tagsApi = {
  list: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/tags`),
  
  get: (workspaceId, tagId) => 
    apiClient.get(`/workspaces/${workspaceId}/tags/${tagId}`),
  
  create: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/tags`, data),
  
  update: (workspaceId, tagId, data) => 
    apiClient.put(`/workspaces/${workspaceId}/tags/${tagId}`, data),
  
  delete: (workspaceId, tagId) => 
    apiClient.delete(`/workspaces/${workspaceId}/tags/${tagId}`),
  
  attachWorkflows: (workspaceId, tagId, workflowIds) => 
    apiClient.post(`/workspaces/${workspaceId}/tags/${tagId}/workflows`, {
      workflow_ids: workflowIds,
    }),
  
  detachWorkflows: (workspaceId, tagId, workflowIds) => 
    apiClient.delete(`/workspaces/${workspaceId}/tags/${tagId}/workflows`, {
      data: { workflow_ids: workflowIds },
    }),
};
```

### React Query Hooks
```javascript
// src/hooks/useVariables.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { variablesApi } from '../api/variables';
import { useWorkspaceStore } from '../stores/workspaceStore';

export function useVariables(params = {}) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['variables', workspaceId, params],
    queryFn: () => variablesApi.list(workspaceId, params),
    enabled: !!workspaceId,
  });
}

export function useVariable(variableId) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['variables', workspaceId, variableId],
    queryFn: () => variablesApi.get(workspaceId, variableId),
    enabled: !!workspaceId && !!variableId,
  });
}

export function useCreateVariable() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (data) => variablesApi.create(workspaceId, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['variables', workspaceId]);
    },
  });
}

export function useUpdateVariable() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: ({ variableId, data }) => 
      variablesApi.update(workspaceId, variableId, data),
    onSuccess: (_, { variableId }) => {
      queryClient.invalidateQueries(['variables', workspaceId]);
      queryClient.invalidateQueries(['variables', workspaceId, variableId]);
    },
  });
}

export function useDeleteVariable() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (variableId) => variablesApi.delete(workspaceId, variableId),
    onSuccess: () => {
      queryClient.invalidateQueries(['variables', workspaceId]);
    },
  });
}

// src/hooks/useTags.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { tagsApi } from '../api/variables';
import { useWorkspaceStore } from '../stores/workspaceStore';

export function useTags() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['tags', workspaceId],
    queryFn: () => tagsApi.list(workspaceId),
    enabled: !!workspaceId,
  });
}

export function useTag(tagId) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['tags', workspaceId, tagId],
    queryFn: () => tagsApi.get(workspaceId, tagId),
    enabled: !!workspaceId && !!tagId,
  });
}

export function useCreateTag() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (data) => tagsApi.create(workspaceId, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['tags', workspaceId]);
    },
  });
}

export function useUpdateTag() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: ({ tagId, data }) => tagsApi.update(workspaceId, tagId, data),
    onSuccess: (_, { tagId }) => {
      queryClient.invalidateQueries(['tags', workspaceId]);
      queryClient.invalidateQueries(['tags', workspaceId, tagId]);
    },
  });
}

export function useDeleteTag() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (tagId) => tagsApi.delete(workspaceId, tagId),
    onSuccess: () => {
      queryClient.invalidateQueries(['tags', workspaceId]);
    },
  });
}

export function useAttachWorkflowsToTag() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: ({ tagId, workflowIds }) => 
      tagsApi.attachWorkflows(workspaceId, tagId, workflowIds),
    onSuccess: (_, { tagId }) => {
      queryClient.invalidateQueries(['tags', workspaceId, tagId]);
      queryClient.invalidateQueries(['workflows', workspaceId]);
    },
  });
}
```

---

## 🎨 UI Components

### Variables List Page
```javascript
// src/pages/Variables.jsx
import { useState } from 'react';
import { useVariables, useDeleteVariable } from '../hooks/useVariables';
import VariableForm from '../components/VariableForm';
import { Eye, EyeOff, Trash2 } from 'lucide-react';

export default function VariablesPage() {
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editingVariable, setEditingVariable] = useState(null);
  const { data: variables, isLoading } = useVariables();
  const deleteVariable = useDeleteVariable();

  const handleDelete = async (variableId) => {
    if (confirm('Delete this variable?')) {
      await deleteVariable.mutateAsync(variableId);
    }
  };

  if (isLoading) return <div>Loading...</div>;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold">Variables</h1>
          <p className="text-gray-600 text-sm mt-1">
            Workspace-wide variables accessible in all workflows
          </p>
        </div>
        <button
          onClick={() => setIsFormOpen(true)}
          className="px-4 py-2 bg-blue-600 text-white rounded"
        >
          Add Variable
        </button>
      </div>

      <div className="bg-white rounded-lg shadow">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-6 py-3 text-left text-sm font-semibold">Key</th>
              <th className="px-6 py-3 text-left text-sm font-semibold">Value</th>
              <th className="px-6 py-3 text-left text-sm font-semibold">Type</th>
              <th className="px-6 py-3 text-left text-sm font-semibold">Description</th>
              <th className="px-6 py-3 text-right text-sm font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody>
            {variables?.data?.map((variable) => (
              <tr key={variable.id} className="border-b hover:bg-gray-50">
                <td className="px-6 py-4 font-mono text-sm">{variable.key}</td>
                <td className="px-6 py-4">
                  {variable.is_secret ? (
                    <span className="text-gray-400">••••••••</span>
                  ) : (
                    <span className="font-mono text-sm">{variable.value}</span>
                  )}
                </td>
                <td className="px-6 py-4">
                  <span className="px-2 py-1 bg-gray-100 rounded text-xs">
                    {variable.type}
                  </span>
                </td>
                <td className="px-6 py-4 text-sm text-gray-600">
                  {variable.description}
                </td>
                <td className="px-6 py-4 text-right">
                  <div className="flex justify-end gap-2">
                    <button
                      onClick={() => {
                        setEditingVariable(variable);
                        setIsFormOpen(true);
                      }}
                      className="text-blue-600 hover:text-blue-800"
                    >
                      Edit
                    </button>
                    <button
                      onClick={() => handleDelete(variable.id)}
                      className="text-red-600 hover:text-red-800"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {isFormOpen && (
        <VariableForm
          variable={editingVariable}
          onClose={() => {
            setIsFormOpen(false);
            setEditingVariable(null);
          }}
        />
      )}
    </div>
  );
}
```

### Variable Form Component
```javascript
// src/components/VariableForm.jsx
import { useForm } from 'react-hook-form';
import { useCreateVariable, useUpdateVariable } from '../hooks/useVariables';

export default function VariableForm({ variable, onClose }) {
  const createVariable = useCreateVariable();
  const updateVariable = useUpdateVariable();
  const { register, handleSubmit, watch } = useForm({
    defaultValues: variable || {
      key: '',
      value: '',
      type: 'string',
      is_secret: false,
      description: '',
    },
  });

  const isSecret = watch('is_secret');

  const onSubmit = async (data) => {
    if (variable) {
      await updateVariable.mutateAsync({
        variableId: variable.id,
        data,
      });
    } else {
      await createVariable.mutateAsync(data);
    }
    onClose();
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-lg">
        <h2 className="text-xl font-bold mb-4">
          {variable ? 'Edit Variable' : 'Create Variable'}
        </h2>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">Key *</label>
            <input
              {...register('key', { required: true })}
              className="w-full border rounded px-3 py-2 font-mono"
              placeholder="my_variable"
              disabled={!!variable} // Can't change key when editing
            />
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">Value *</label>
            <input
              {...register('value', { required: true })}
              type={isSecret ? 'password' : 'text'}
              className="w-full border rounded px-3 py-2 font-mono"
              placeholder="Value"
            />
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">Type</label>
            <select
              {...register('type')}
              className="w-full border rounded px-3 py-2"
            >
              <option value="string">String</option>
              <option value="number">Number</option>
              <option value="boolean">Boolean</option>
              <option value="json">JSON</option>
            </select>
          </div>

          <div>
            <label className="flex items-center gap-2">
              <input
                {...register('is_secret')}
                type="checkbox"
                className="rounded"
              />
              <span className="text-sm">Secret (will be masked in UI)</span>
            </label>
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">Description</label>
            <textarea
              {...register('description')}
              className="w-full border rounded px-3 py-2"
              rows={3}
              placeholder="What is this variable used for?"
            />
          </div>

          <div className="flex gap-2 pt-4">
            <button
              type="submit"
              className="flex-1 px-4 py-2 bg-blue-600 text-white rounded"
            >
              {variable ? 'Update' : 'Create'}
            </button>
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 border rounded"
            >
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
```

### Tag Manager Component
```javascript
// src/components/TagManager.jsx
import { useState } from 'react';
import { useTags, useCreateTag, useDeleteTag } from '../hooks/useTags';
import { X } from 'lucide-react';

export default function TagManager() {
  const [newTagName, setNewTagName] = useState('');
  const [newTagColor, setNewTagColor] = useState('#3b82f6');
  const { data: tags } = useTags();
  const createTag = useCreateTag();
  const deleteTag = useDeleteTag();

  const handleCreate = async (e) => {
    e.preventDefault();
    if (!newTagName.trim()) return;

    await createTag.mutateAsync({
      name: newTagName,
      color: newTagColor,
    });
    setNewTagName('');
  };

  const handleDelete = async (tagId) => {
    if (confirm('Delete this tag? It will be removed from all workflows.')) {
      await deleteTag.mutateAsync(tagId);
    }
  };

  return (
    <div className="p-6">
      <h2 className="text-xl font-bold mb-4">Tags</h2>

      <form onSubmit={handleCreate} className="flex gap-2 mb-6">
        <input
          value={newTagName}
          onChange={(e) => setNewTagName(e.target.value)}
          placeholder="New tag name"
          className="flex-1 border rounded px-3 py-2"
        />
        <input
          type="color"
          value={newTagColor}
          onChange={(e) => setNewTagColor(e.target.value)}
          className="w-12 h-10 border rounded cursor-pointer"
        />
        <button
          type="submit"
          disabled={createTag.isPending}
          className="px-4 py-2 bg-blue-600 text-white rounded"
        >
          Add
        </button>
      </form>

      <div className="flex flex-wrap gap-2">
        {tags?.data?.map((tag) => (
          <div
            key={tag.id}
            className="flex items-center gap-2 px-3 py-1 rounded-full border"
            style={{ borderColor: tag.color }}
          >
            <div
              className="w-3 h-3 rounded-full"
              style={{ backgroundColor: tag.color }}
            />
            <span className="text-sm">{tag.name}</span>
            <span className="text-xs text-gray-500">({tag.workflows_count})</span>
            <button
              onClick={() => handleDelete(tag.id)}
              className="ml-1 text-gray-400 hover:text-red-600"
            >
              <X className="w-3 h-3" />
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
```

### Tag Filter for Workflow List
```javascript
// src/components/WorkflowTagFilter.jsx
import { useTags } from '../hooks/useTags';

export default function WorkflowTagFilter({ selectedTags, onChange }) {
  const { data: tags } = useTags();

  const toggleTag = (tagId) => {
    if (selectedTags.includes(tagId)) {
      onChange(selectedTags.filter(id => id !== tagId));
    } else {
      onChange([...selectedTags, tagId]);
    }
  };

  return (
    <div className="flex flex-wrap gap-2 mb-4">
      <span className="text-sm font-medium text-gray-700">Filter by tags:</span>
      {tags?.data?.map((tag) => (
        <button
          key={tag.id}
          onClick={() => toggleTag(tag.id)}
          className={`px-3 py-1 rounded-full text-sm border transition ${
            selectedTags.includes(tag.id)
              ? 'bg-blue-50 border-blue-500'
              : 'border-gray-300 hover:border-gray-400'
          }`}
          style={{
            borderColor: selectedTags.includes(tag.id) ? tag.color : undefined,
          }}
        >
          <div className="flex items-center gap-2">
            <div
              className="w-2 h-2 rounded-full"
              style={{ backgroundColor: tag.color }}
            />
            {tag.name}
          </div>
        </button>
      ))}
    </div>
  );
}
```

---

## 💡 Common Use Cases

### 1. Use Variable in Workflow Expression
```javascript
// In workflow node configuration
const expression = `{{$vars.api_base_url}}/users`;

// Variables are available in workflow context as $vars.*
```

### 2. Bulk Tag Workflows
```javascript
const attachTagToWorkflows = useAttachWorkflowsToTag();

const bulkTag = async (tagId, workflowIds) => {
  await attachTagToWorkflows.mutateAsync({ tagId, workflowIds });
};
```

### 3. Environment-based Variables
```javascript
// Create different variables for different environments
const variables = [
  { key: 'api_base_url', value: 'https://api.prod.example.com' },
  { key: 'debug_mode', value: 'false' },
  { key: 'max_timeout', value: '30000' },
];
```

---

## 🔒 Security Notes

1. **Secret Variables**: Always mark sensitive data (passwords, API keys) as `is_secret: true`
2. **Access Control**: Variables are workspace-scoped; members can view based on role
3. **Audit Logging**: Variable changes should be logged in activity logs
4. **Encryption**: Backend should encrypt secret variables at rest

---

## 🎯 Next Steps

- Read [Module 8: Webhooks](./08-webhooks.md)
- Implement variable autocomplete in workflow expression editor
- Add variable usage tracking
