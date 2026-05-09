# 🚀 Module 15: Advanced Workflow Features

**Workflow versioning, sharing, sticky notes, pinned data, and import/export**

**APIs:** `/api/v1/workspaces/{workspace}/workflows/{workflow}/versions/*`, `/api/v1/workspaces/{workspace}/workflows/{workflow}/shares/*`, `/api/v1/workspaces/{workspace}/workflows/import`, `/api/v1/workspaces/{workspace}/workflows/{workflow}/export`  
**Components:** VersionControl, WorkflowShare, StickyNotes, PinnedDataPanel

---

## 🔗 API Endpoints

### Workflow Versioning

#### 1. List Workflow Versions
```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/versions
Authorization: Bearer {token}

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "version_number": 3,
      "name": "v3 - Added error handling",
      "is_published": true,
      "is_current": true,
      "created_by": "John Doe",
      "created_at": "2024-01-15T10:00:00Z",
      "node_count": 8,
      "changes_summary": "Added retry logic to API call node"
    },
    {
      "id": "uuid",
      "version_number": 2,
      "is_published": false,
      "created_at": "2024-01-10T10:00:00Z"
    }
  ]
}
```

#### 2. Create New Version
```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/versions
Content-Type: application/json

{
  "name": "v4 - Performance improvements",
  "description": "Optimized data processing nodes"
}

Response (201):
{
  "data": {
    "id": "uuid",
    "version_number": 4,
    "name": "v4 - Performance improvements"
  }
}
```

#### 3. Get Version Details
```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/{version_id}

Response (200):
{
  "data": {
    "id": "uuid",
    "version_number": 3,
    "workflow_definition": {
      "nodes": [...],
      "connections": [...]
    },
    "created_by": "John Doe",
    "created_at": "2024-01-15T10:00:00Z"
  }
}
```

#### 4. Publish Version
```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/{version_id}/publish

Response (200):
{
  "message": "Version published successfully",
  "data": {
    "version_number": 3,
    "is_published": true
  }
}
```

#### 5. Rollback to Version
```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/{version_id}/rollback

Response (200):
{
  "message": "Rolled back to version 2",
  "data": {
    "current_version": 2
  }
}
```

#### 6. Compare Versions (Diff)
```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/versions/diff

Query Parameters:
- from_version: Version ID
- to_version: Version ID

Response (200):
{
  "data": {
    "nodes_added": [
      {
        "id": "node_123",
        "type": "retry",
        "name": "Retry Logic"
      }
    ],
    "nodes_removed": [],
    "nodes_modified": [
      {
        "id": "node_456",
        "changes": {
          "timeout": { "old": 30, "new": 60 }
        }
      }
    ],
    "connections_changed": true
  }
}
```

### Workflow Sharing

#### 7. List Workflow Shares
```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/shares

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "token": "abc123xyz",
      "url": "https://app.example.com/shared/abc123xyz",
      "access_level": "view",
      "is_public": true,
      "expires_at": "2024-02-15T00:00:00Z",
      "view_count": 42,
      "created_at": "2024-01-15T00:00:00Z"
    }
  ]
}
```

#### 8. Create Share Link
```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/shares
Content-Type: application/json

{
  "access_level": "view", // view or clone
  "is_public": true,
  "expires_at": "2024-12-31T00:00:00Z"
}

Response (201):
{
  "data": {
    "id": "uuid",
    "token": "abc123xyz",
    "url": "https://app.example.com/shared/abc123xyz"
  }
}
```

#### 9. Update Share
```http
PUT /api/v1/workspaces/{workspace}/workflows/{workflow}/shares/{share_id}
Content-Type: application/json

{
  "is_public": false,
  "expires_at": null
}
```

#### 10. Delete Share
```http
DELETE /api/v1/workspaces/{workspace}/workflows/{workflow}/shares/{share_id}

Response (204): No Content
```

### Sticky Notes (Workflow Canvas Annotations)

#### 11. List Sticky Notes
```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/sticky-notes

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "content": "TODO: Add error handling here",
      "color": "#fef3c7",
      "position": {
        "x": 100,
        "y": 200
      },
      "size": {
        "width": 200,
        "height": 150
      },
      "created_by": "John Doe",
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

#### 12. Create Sticky Note
```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/sticky-notes
Content-Type: application/json

{
  "content": "Important: Rate limit is 100 requests/min",
  "color": "#fef3c7",
  "position": { "x": 300, "y": 400 },
  "size": { "width": 250, "height": 100 }
}

Response (201):
{
  "data": {
    "id": "uuid",
    "content": "Important...",
    "color": "#fef3c7"
  }
}
```

#### 13. Update Sticky Note
```http
PUT /api/v1/workspaces/{workspace}/workflows/{workflow}/sticky-notes/{note_id}
Content-Type: application/json

{
  "content": "Updated note",
  "position": { "x": 350, "y": 450 }
}
```

#### 14. Delete Sticky Note
```http
DELETE /api/v1/workspaces/{workspace}/workflows/{workflow}/sticky-notes/{note_id}

Response (204): No Content
```

### Pinned Node Data (Test Data)

#### 15. List Pinned Data
```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/pinned-data

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "node_id": "node_123",
      "node_name": "HTTP Request",
      "data": {
        "body": {"test": "data"},
        "headers": {"Content-Type": "application/json"}
      },
      "is_active": true,
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

#### 16. Create Pinned Data
```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/pinned-data
Content-Type: application/json

{
  "node_id": "node_123",
  "data": {
    "test_user": {
      "email": "test@example.com",
      "name": "Test User"
    }
  }
}

Response (201):
{
  "data": {
    "id": "uuid",
    "node_id": "node_123"
  }
}
```

#### 17. Toggle Pinned Data
```http
POST /api/v1/workspaces/{workspace}/workflows/{workflow}/pinned-data/{pinned_id}/toggle

Response (200):
{
  "data": {
    "is_active": false
  }
}
```

### Import/Export Workflows

#### 18. Export Workflow
```http
GET /api/v1/workspaces/{workspace}/workflows/{workflow}/export

Query Parameters:
- format: json (default)

Response (200):
{
  "workflow": {
    "name": "User Onboarding",
    "description": "...",
    "nodes": [...],
    "connections": [...],
    "variables": [...],
    "credentials_required": [...]
  },
  "metadata": {
    "exported_at": "2024-01-15T10:00:00Z",
    "version": "1.0"
  }
}
```

#### 19. Import Workflow
```http
POST /api/v1/workspaces/{workspace}/workflows/import
Content-Type: application/json

{
  "workflow": {
    "name": "Imported Workflow",
    "nodes": [...],
    "connections": [...]
  },
  "overwrite_existing": false
}

Response (201):
{
  "data": {
    "workflow_id": "uuid",
    "name": "Imported Workflow",
    "nodes_imported": 8
  }
}
```

#### 20. Build Workflow from Natural Language
```http
POST /api/v1/workspaces/{workspace}/workflows/build
Content-Type: application/json

{
  "description": "Send a welcome email when a user signs up, wait 3 days, then send a follow-up email"
}

Response (201):
{
  "data": {
    "workflow_id": "uuid",
    "name": "User Welcome Sequence",
    "nodes": [
      {
        "type": "trigger",
        "name": "User Signup"
      },
      {
        "type": "email",
        "name": "Welcome Email"
      },
      {
        "type": "wait",
        "config": { "duration": "3d" }
      },
      {
        "type": "email",
        "name": "Follow-up Email"
      }
    ]
  }
}
```

---

## 🗄️ State Management

### API Client
```javascript
// src/api/advancedWorkflow.js
import apiClient from './client';

export const advancedWorkflowApi = {
  // Versions
  listVersions: (workspaceId, workflowId) => 
    apiClient.get(`/workspaces/${workspaceId}/workflows/${workflowId}/versions`),
  
  createVersion: (workspaceId, workflowId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/versions`, data),
  
  getVersion: (workspaceId, workflowId, versionId) => 
    apiClient.get(`/workspaces/${workspaceId}/workflows/${workflowId}/versions/${versionId}`),
  
  publishVersion: (workspaceId, workflowId, versionId) => 
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/versions/${versionId}/publish`),
  
  rollbackVersion: (workspaceId, workflowId, versionId) => 
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/versions/${versionId}/rollback`),
  
  compareVersions: (workspaceId, workflowId, fromVersion, toVersion) => 
    apiClient.get(`/workspaces/${workspaceId}/workflows/${workflowId}/versions/diff`, {
      params: { from_version: fromVersion, to_version: toVersion },
    }),
  
  // Shares
  listShares: (workspaceId, workflowId) => 
    apiClient.get(`/workspaces/${workspaceId}/workflows/${workflowId}/shares`),
  
  createShare: (workspaceId, workflowId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/shares`, data),
  
  updateShare: (workspaceId, workflowId, shareId, data) => 
    apiClient.put(`/workspaces/${workspaceId}/workflows/${workflowId}/shares/${shareId}`, data),
  
  deleteShare: (workspaceId, workflowId, shareId) => 
    apiClient.delete(`/workspaces/${workspaceId}/workflows/${workflowId}/shares/${shareId}`),
  
  // Sticky Notes
  listStickyNotes: (workspaceId, workflowId) => 
    apiClient.get(`/workspaces/${workspaceId}/workflows/${workflowId}/sticky-notes`),
  
  createStickyNote: (workspaceId, workflowId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/sticky-notes`, data),
  
  updateStickyNote: (workspaceId, workflowId, noteId, data) => 
    apiClient.put(`/workspaces/${workspaceId}/workflows/${workflowId}/sticky-notes/${noteId}`, data),
  
  deleteStickyNote: (workspaceId, workflowId, noteId) => 
    apiClient.delete(`/workspaces/${workspaceId}/workflows/${workflowId}/sticky-notes/${noteId}`),
  
  // Pinned Data
  listPinnedData: (workspaceId, workflowId) => 
    apiClient.get(`/workspaces/${workspaceId}/workflows/${workflowId}/pinned-data`),
  
  createPinnedData: (workspaceId, workflowId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/pinned-data`, data),
  
  togglePinnedData: (workspaceId, workflowId, pinnedId) => 
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/pinned-data/${pinnedId}/toggle`),
  
  // Import/Export
  exportWorkflow: (workspaceId, workflowId) => 
    apiClient.get(`/workspaces/${workspaceId}/workflows/${workflowId}/export`),
  
  importWorkflow: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/workflows/import`, data),
  
  buildWorkflow: (workspaceId, description) => 
    apiClient.post(`/workspaces/${workspaceId}/workflows/build`, { description }),
};
```

---

## 🎨 UI Components

### Version Control Panel
```javascript
// src/components/VersionControl.jsx
import { useState } from 'react';
import { useVersions, useCreateVersion, useRollback } from '../hooks/useAdvancedWorkflow';
import { History, GitBranch, RotateCcw } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

export default function VersionControl({ workflowId }) {
  const { data: versions } = useVersions(workflowId);
  const createVersion = useCreateVersion();
  const rollback = useRollback();
  const [versionName, setVersionName] = useState('');

  const handleCreateVersion = async () => {
    await createVersion.mutateAsync({
      workflowId,
      data: { name: versionName },
    });
    setVersionName('');
  };

  const handleRollback = async (versionId) => {
    if (confirm('Rollback to this version? Current changes will be saved as a new version.')) {
      await rollback.mutateAsync({ workflowId, versionId });
    }
  };

  return (
    <div className="bg-white rounded-lg shadow p-6">
      <div className="flex items-center gap-2 mb-4">
        <History className="w-5 h-5" />
        <h2 className="text-lg font-semibold">Version History</h2>
      </div>

      {/* Create Version */}
      <div className="mb-6 flex gap-2">
        <input
          value={versionName}
          onChange={(e) => setVersionName(e.target.value)}
          placeholder="Version name (e.g., v2 - Bug fixes)"
          className="flex-1 border rounded px-3 py-2"
        />
        <button
          onClick={handleCreateVersion}
          disabled={!versionName.trim() || createVersion.isPending}
          className="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50"
        >
          Save Version
        </button>
      </div>

      {/* Version List */}
      <div className="space-y-2">
        {versions?.data?.map((version) => (
          <div
            key={version.id}
            className={`border rounded-lg p-4 ${
              version.is_current ? 'ring-2 ring-blue-500' : ''
            }`}
          >
            <div className="flex items-start justify-between">
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <GitBranch className="w-4 h-4 text-gray-600" />
                  <h3 className="font-semibold">
                    {version.name || `Version ${version.version_number}`}
                  </h3>
                  {version.is_current && (
                    <span className="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">
                      Current
                    </span>
                  )}
                  {version.is_published && (
                    <span className="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">
                      Published
                    </span>
                  )}
                </div>
                <p className="text-sm text-gray-600 mt-1">
                  {version.changes_summary}
                </p>
                <p className="text-xs text-gray-500 mt-2">
                  {version.created_by} •{' '}
                  {formatDistanceToNow(new Date(version.created_at), { addSuffix: true })}
                </p>
              </div>
              {!version.is_current && (
                <button
                  onClick={() => handleRollback(version.id)}
                  className="flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700"
                >
                  <RotateCcw className="w-4 h-4" />
                  Rollback
                </button>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
```

### Share Workflow Modal
```javascript
// src/components/ShareWorkflow.jsx
import { useState } from 'react';
import { useCreateShare } from '../hooks/useAdvancedWorkflow';
import { Copy, Check, Share2 } from 'lucide-react';

export default function ShareWorkflow({ workflowId, onClose }) {
  const createShare = useCreateShare();
  const [shareUrl, setShareUrl] = useState(null);
  const [copied, setCopied] = useState(false);

  const handleCreateShare = async () => {
    const result = await createShare.mutateAsync({
      workflowId,
      data: {
        access_level: 'view',
        is_public: true,
      },
    });
    setShareUrl(result.data.url);
  };

  const copyToClipboard = () => {
    navigator.clipboard.writeText(shareUrl);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-md">
        <div className="flex items-center gap-2 mb-4">
          <Share2 className="w-5 h-5" />
          <h2 className="text-xl font-bold">Share Workflow</h2>
        </div>

        {!shareUrl ? (
          <div>
            <p className="text-gray-600 mb-4">
              Create a shareable link that anyone can view or clone.
            </p>
            <button
              onClick={handleCreateShare}
              disabled={createShare.isPending}
              className="w-full px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
              {createShare.isPending ? 'Creating...' : 'Create Share Link'}
            </button>
          </div>
        ) : (
          <div>
            <p className="text-sm text-gray-600 mb-2">Share this link:</p>
            <div className="flex gap-2">
              <input
                value={shareUrl}
                readOnly
                className="flex-1 border rounded px-3 py-2 text-sm bg-gray-50"
              />
              <button
                onClick={copyToClipboard}
                className="px-4 py-2 border rounded hover:bg-gray-50"
              >
                {copied ? (
                  <Check className="w-4 h-4 text-green-600" />
                ) : (
                  <Copy className="w-4 h-4" />
                )}
              </button>
            </div>
          </div>
        )}

        <button
          onClick={onClose}
          className="w-full mt-4 px-4 py-2 border rounded"
        >
          Close
        </button>
      </div>
    </div>
  );
}
```

### Sticky Notes on Canvas
```javascript
// src/components/StickyNote.jsx
import { useState } from 'react';
import { useUpdateStickyNote, useDeleteStickyNote } from '../hooks/useAdvancedWorkflow';
import { X } from 'lucide-react';
import Draggable from 'react-draggable';

export default function StickyNote({ note, workflowId }) {
  const [content, setContent] = useState(note.content);
  const updateNote = useUpdateStickyNote();
  const deleteNote = useDeleteStickyNote();

  const handleDragStop = (e, data) => {
    updateNote.mutate({
      workflowId,
      noteId: note.id,
      data: {
        position: { x: data.x, y: data.y },
      },
    });
  };

  const handleContentChange = () => {
    updateNote.mutate({
      workflowId,
      noteId: note.id,
      data: { content },
    });
  };

  return (
    <Draggable
      defaultPosition={note.position}
      onStop={handleDragStop}
      handle=".drag-handle"
    >
      <div
        className="absolute shadow-lg"
        style={{
          backgroundColor: note.color,
          width: note.size.width,
          height: note.size.height,
        }}
      >
        <div className="drag-handle cursor-move p-2 border-b flex justify-between">
          <span className="text-xs font-medium">Note</span>
          <button
            onClick={() => deleteNote.mutate({ workflowId, noteId: note.id })}
            className="text-gray-500 hover:text-red-600"
          >
            <X className="w-3 h-3" />
          </button>
        </div>
        <textarea
          value={content}
          onChange={(e) => setContent(e.target.value)}
          onBlur={handleContentChange}
          className="w-full h-full p-2 bg-transparent border-none focus:outline-none resize-none"
        />
      </div>
    </Draggable>
  );
}
```

---

## 💡 Common Use Cases

### 1. Export and Download Workflow
```javascript
const handleExport = async () => {
  const data = await advancedWorkflowApi.exportWorkflow(workspaceId, workflowId);
  const blob = new Blob([JSON.stringify(data.data, null, 2)], {
    type: 'application/json',
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `workflow-${workflowId}.json`;
  a.click();
};
```

### 2. Import from File
```javascript
const handleImport = async (file) => {
  const content = await file.text();
  const workflow = JSON.parse(content);
  await advancedWorkflowApi.importWorkflow(workspaceId, { workflow });
};
```

### 3. AI Workflow Builder
```javascript
const description = "Send a Slack message when a new user signs up";
const result = await advancedWorkflowApi.buildWorkflow(workspaceId, description);
navigate(`/workflows/${result.data.workflow_id}`);
```

---

## 🎯 Conclusion

You've now completed all 15 modules of the React Frontend Integration Guide!

**What you've learned:**
- Authentication & user management
- Workspace & team collaboration
- Visual workflow editor integration
- Real-time execution monitoring
- Credentials & security
- Variables, tags, webhooks
- Templates & sharing
- Notifications & preferences
- Settings & billing
- Analytics & activity logs
- AI Agents system
- Advanced workflow features

**Next Steps:**
1. Start with Phase 1 (Authentication + Workspace)
2. Build incrementally following the implementation order
3. Use React Query for all API calls
4. Implement proper error handling
5. Add loading states and optimistic updates
6. Test thoroughly before deploying

**Happy Building! 🚀**
