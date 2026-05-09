# 📚 Module 9: Templates Marketplace

**Browse, use, and share workflow templates**

**APIs:** `/api/v1/templates/*`, `/api/v1/workspaces/{workspace}/templates/{id}/use`  
**Components:** TemplateGallery, TemplateCard, TemplateDetail, TemplatePreview

---

## 🔗 API Endpoints

### Public Template Browsing (No Auth Required)

#### 1. List Public Templates
```http
GET /api/v1/templates

Query Parameters:
- search (optional): Search by name or description
- category (optional): Filter by category
- sort (optional): popular, newest, most_used
- page (optional): Pagination

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "name": "User Onboarding Email Sequence",
      "description": "Automated email sequence for new users",
      "category": "Marketing",
      "author": "LinkFlow Team",
      "thumbnail": "https://...",
      "tags": ["email", "automation", "onboarding"],
      "use_count": 1542,
      "rating": 4.8,
      "is_featured": true,
      "created_at": "2024-01-01T00:00:00Z"
    },
    {
      "id": "uuid",
      "name": "Slack Alert on High-Value Purchase",
      "description": "Send Slack notification when order exceeds threshold",
      "category": "Notifications",
      "use_count": 892
    }
  ],
  "meta": {
    "total": 50,
    "current_page": 1,
    "per_page": 20
  }
}
```

#### 2. Get Template Details
```http
GET /api/v1/templates/{id}

Response (200):
{
  "data": {
    "id": "uuid",
    "name": "User Onboarding Email Sequence",
    "description": "Automated email sequence...",
    "long_description": "This workflow sends a series of onboarding emails...",
    "category": "Marketing",
    "author": "LinkFlow Team",
    "thumbnail": "https://...",
    "screenshots": [
      "https://...",
      "https://..."
    ],
    "tags": ["email", "automation"],
    "use_count": 1542,
    "rating": 4.8,
    "reviews_count": 127,
    "workflow_preview": {
      "nodes": [
        {
          "type": "trigger",
          "name": "User Created"
        },
        {
          "type": "email",
          "name": "Welcome Email"
        }
      ],
      "node_count": 5
    },
    "required_credentials": [
      {
        "type": "sendgrid",
        "name": "SendGrid"
      }
    ],
    "required_variables": [
      {
        "key": "sender_email",
        "description": "Email address to send from"
      }
    ],
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-15T00:00:00Z"
  }
}
```

### Template Usage (Requires Auth + Workspace)

#### 3. Use Template (Clone to Workspace)
```http
POST /api/v1/workspaces/{workspace}/templates/{template_id}/use
Authorization: Bearer {token}
Content-Type: application/json

{
  "workflow_name": "My Onboarding Sequence",
  "customize": {
    "variables": {
      "sender_email": "noreply@mycompany.com"
    }
  }
}

Response (201):
{
  "data": {
    "workflow_id": "uuid",
    "name": "My Onboarding Sequence",
    "message": "Template cloned successfully"
  }
}
```

### Shared Workflows (Public Sharing)

#### 4. View Shared Workflow (Public)
```http
GET /api/v1/shared/{share_token}

Response (200):
{
  "data": {
    "workflow_name": "My Custom Workflow",
    "description": "...",
    "author": "John Doe",
    "workflow_definition": {
      "nodes": [...],
      "connections": [...]
    },
    "shared_at": "2024-01-15T00:00:00Z"
  }
}
```

#### 5. Clone Shared Workflow to Workspace
```http
POST /api/v1/workspaces/{workspace}/shared/{share_token}/clone
Authorization: Bearer {token}

Response (201):
{
  "data": {
    "workflow_id": "uuid",
    "name": "Cloned Workflow"
  }
}
```

---

## 🗄️ State Management

### API Client
```javascript
// src/api/templates.js
import apiClient from './client';

export const templatesApi = {
  // Public endpoints (no workspace needed)
  list: (params = {}) => 
    apiClient.get('/templates', { params }),
  
  get: (templateId) => 
    apiClient.get(`/templates/${templateId}`),
  
  // Workspace endpoints
  use: (workspaceId, templateId, data = {}) => 
    apiClient.post(`/workspaces/${workspaceId}/templates/${templateId}/use`, data),
  
  // Shared workflows
  viewShared: (shareToken) => 
    apiClient.get(`/shared/${shareToken}`),
  
  cloneShared: (workspaceId, shareToken) => 
    apiClient.post(`/workspaces/${workspaceId}/shared/${shareToken}/clone`),
};
```

### React Query Hooks
```javascript
// src/hooks/useTemplates.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { templatesApi } from '../api/templates';
import { useWorkspaceStore } from '../stores/workspaceStore';

export function useTemplates(params = {}) {
  return useQuery({
    queryKey: ['templates', params],
    queryFn: () => templatesApi.list(params),
    staleTime: 5 * 60 * 1000, // Cache for 5 minutes
  });
}

export function useTemplate(templateId) {
  return useQuery({
    queryKey: ['templates', templateId],
    queryFn: () => templatesApi.get(templateId),
    enabled: !!templateId,
  });
}

export function useTemplateActions() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  const useTemplate = useMutation({
    mutationFn: ({ templateId, data }) => 
      templatesApi.use(workspaceId, templateId, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['workflows', workspaceId]);
    },
  });

  const cloneShared = useMutation({
    mutationFn: (shareToken) => 
      templatesApi.cloneShared(workspaceId, shareToken),
    onSuccess: () => {
      queryClient.invalidateQueries(['workflows', workspaceId]);
    },
  });

  return { useTemplate, cloneShared };
}

export function useSharedWorkflow(shareToken) {
  return useQuery({
    queryKey: ['shared-workflow', shareToken],
    queryFn: () => templatesApi.viewShared(shareToken),
    enabled: !!shareToken,
  });
}
```

---

## 🎨 UI Components

### Template Gallery Page
```javascript
// src/pages/TemplateGallery.jsx
import { useState } from 'react';
import { useTemplates } from '../hooks/useTemplates';
import TemplateCard from '../components/TemplateCard';
import { Search } from 'lucide-react';

export default function TemplateGallery() {
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');
  const { data: templates, isLoading } = useTemplates({ search, category });

  const categories = ['All', 'Marketing', 'Sales', 'Support', 'Notifications', 'Data Processing'];

  return (
    <div className="p-6">
      <div className="mb-6">
        <h1 className="text-3xl font-bold">Workflow Templates</h1>
        <p className="text-gray-600 mt-1">
          Start with pre-built workflows and customize to your needs
        </p>
      </div>

      {/* Search & Filters */}
      <div className="mb-6 flex gap-4">
        <div className="flex-1 relative">
          <Search className="absolute left-3 top-2.5 w-5 h-5 text-gray-400" />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search templates..."
            className="w-full pl-10 pr-4 py-2 border rounded-lg"
          />
        </div>
        <select
          value={category}
          onChange={(e) => setCategory(e.target.value)}
          className="border rounded-lg px-4 py-2"
        >
          {categories.map(cat => (
            <option key={cat} value={cat === 'All' ? '' : cat}>
              {cat}
            </option>
          ))}
        </select>
      </div>

      {/* Featured Templates */}
      {!search && !category && (
        <div className="mb-8">
          <h2 className="text-xl font-semibold mb-4">Featured Templates</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {templates?.data
              ?.filter(t => t.is_featured)
              .map(template => (
                <TemplateCard key={template.id} template={template} featured />
              ))}
          </div>
        </div>
      )}

      {/* All Templates */}
      <div>
        <h2 className="text-xl font-semibold mb-4">
          {search || category ? 'Search Results' : 'All Templates'}
        </h2>
        {isLoading ? (
          <div>Loading templates...</div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {templates?.data?.map(template => (
              <TemplateCard key={template.id} template={template} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
```

### Template Card Component
```javascript
// src/components/TemplateCard.jsx
import { Star, Download } from 'lucide-react';
import { Link } from 'react-router-dom';

export default function TemplateCard({ template, featured = false }) {
  return (
    <Link
      to={`/templates/${template.id}`}
      className={`block bg-white border rounded-lg overflow-hidden hover:shadow-lg transition ${
        featured ? 'ring-2 ring-blue-500' : ''
      }`}
    >
      {/* Thumbnail */}
      <div className="h-40 bg-gradient-to-br from-blue-500 to-purple-600">
        {template.thumbnail && (
          <img
            src={template.thumbnail}
            alt={template.name}
            className="w-full h-full object-cover"
          />
        )}
      </div>

      {/* Content */}
      <div className="p-4">
        <div className="flex items-start justify-between">
          <h3 className="font-semibold text-lg flex-1">{template.name}</h3>
          {featured && (
            <span className="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded">
              Featured
            </span>
          )}
        </div>

        <p className="text-sm text-gray-600 mt-2 line-clamp-2">
          {template.description}
        </p>

        {/* Meta */}
        <div className="mt-4 flex items-center gap-4 text-sm text-gray-600">
          <div className="flex items-center gap-1">
            <Star className="w-4 h-4 text-yellow-500 fill-yellow-500" />
            <span>{template.rating}</span>
          </div>
          <div className="flex items-center gap-1">
            <Download className="w-4 h-4" />
            <span>{template.use_count}</span>
          </div>
        </div>

        {/* Tags */}
        <div className="mt-3 flex flex-wrap gap-1">
          {template.tags?.slice(0, 3).map(tag => (
            <span
              key={tag}
              className="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded"
            >
              {tag}
            </span>
          ))}
        </div>
      </div>
    </Link>
  );
}
```

### Template Detail Page
```javascript
// src/pages/TemplateDetail.jsx
import { useParams, useNavigate } from 'react-router-dom';
import { useTemplate, useTemplateActions } from '../hooks/useTemplates';
import { useState } from 'react';
import { Star, Download, ArrowLeft } from 'lucide-react';

export default function TemplateDetail() {
  const { templateId } = useParams();
  const navigate = useNavigate();
  const { data: template, isLoading } = useTemplate(templateId);
  const { useTemplate: useTemplateMutation } = useTemplateActions();
  const [workflowName, setWorkflowName] = useState('');

  const handleUseTemplate = async () => {
    try {
      const result = await useTemplateMutation.mutateAsync({
        templateId,
        data: {
          workflow_name: workflowName || template.data.name,
        },
      });
      navigate(`/workflows/${result.data.workflow_id}`);
    } catch (error) {
      alert('Failed to use template');
    }
  };

  if (isLoading) return <div>Loading...</div>;
  if (!template) return <div>Template not found</div>;

  return (
    <div className="p-6 max-w-5xl mx-auto">
      <button
        onClick={() => navigate('/templates')}
        className="flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-6"
      >
        <ArrowLeft className="w-4 h-4" />
        Back to Templates
      </button>

      <div className="bg-white rounded-lg shadow-lg overflow-hidden">
        {/* Header */}
        <div className="bg-gradient-to-r from-blue-500 to-purple-600 p-8 text-white">
          <h1 className="text-3xl font-bold">{template.data.name}</h1>
          <p className="mt-2 text-blue-100">{template.data.description}</p>
          <div className="mt-4 flex items-center gap-6">
            <div className="flex items-center gap-2">
              <Star className="w-5 h-5 fill-yellow-300 text-yellow-300" />
              <span>{template.data.rating} ({template.data.reviews_count} reviews)</span>
            </div>
            <div className="flex items-center gap-2">
              <Download className="w-5 h-5" />
              <span>{template.data.use_count} uses</span>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="p-8">
          {/* Description */}
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-3">About This Template</h2>
            <p className="text-gray-700 whitespace-pre-line">
              {template.data.long_description}
            </p>
          </div>

          {/* Workflow Preview */}
          <div className="mb-8">
            <h2 className="text-xl font-semibold mb-3">Workflow Overview</h2>
            <div className="bg-gray-50 p-4 rounded-lg">
              <div className="flex items-center gap-2 text-sm text-gray-600 mb-4">
                <span>{template.data.workflow_preview.node_count} nodes</span>
              </div>
              <div className="space-y-2">
                {template.data.workflow_preview.nodes.map((node, idx) => (
                  <div key={idx} className="flex items-center gap-3">
                    <div className="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white text-sm">
                      {idx + 1}
                    </div>
                    <div>
                      <div className="font-medium">{node.name}</div>
                      <div className="text-xs text-gray-600">{node.type}</div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Requirements */}
          {(template.data.required_credentials?.length > 0 || 
            template.data.required_variables?.length > 0) && (
            <div className="mb-8">
              <h2 className="text-xl font-semibold mb-3">Requirements</h2>
              
              {template.data.required_credentials?.length > 0 && (
                <div className="mb-4">
                  <h3 className="font-medium mb-2">Credentials:</h3>
                  <ul className="list-disc list-inside space-y-1">
                    {template.data.required_credentials.map((cred, idx) => (
                      <li key={idx} className="text-gray-700">
                        {cred.name} ({cred.type})
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {template.data.required_variables?.length > 0 && (
                <div>
                  <h3 className="font-medium mb-2">Variables:</h3>
                  <ul className="list-disc list-inside space-y-1">
                    {template.data.required_variables.map((variable, idx) => (
                      <li key={idx} className="text-gray-700">
                        <code className="bg-gray-100 px-1 rounded">{variable.key}</code>
                        {' - '}{variable.description}
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          )}

          {/* Use Template */}
          <div className="border-t pt-6">
            <h2 className="text-xl font-semibold mb-3">Use This Template</h2>
            <div className="flex gap-4">
              <input
                value={workflowName}
                onChange={(e) => setWorkflowName(e.target.value)}
                placeholder={template.data.name}
                className="flex-1 border rounded px-4 py-2"
              />
              <button
                onClick={handleUseTemplate}
                disabled={useTemplateMutation.isPending}
                className="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
              >
                {useTemplateMutation.isPending ? 'Creating...' : 'Use Template'}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
```

---

## 💡 Common Use Cases

### 1. Browse by Category
```javascript
const { data: marketingTemplates } = useTemplates({ category: 'Marketing' });
```

### 2. Search Templates
```javascript
const { data: results } = useTemplates({ search: 'email automation' });
```

### 3. Use Template with Custom Name
```javascript
const { useTemplate } = useTemplateActions();

await useTemplate.mutateAsync({
  templateId: 'template-uuid',
  data: {
    workflow_name: 'My Custom Workflow',
  },
});
```

---

## 🎯 Next Steps

- Read [Module 10: Team Management](./10-team.md)
- Implement template rating/review system
- Add template customization wizard
