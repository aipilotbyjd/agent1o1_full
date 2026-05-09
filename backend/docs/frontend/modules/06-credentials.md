# 🔐 Module 6: Credentials Management

**Securely store API keys, OAuth tokens, and connection credentials**

**APIs:** `/api/v1/workspaces/{workspace}/credentials/*`, `/api/v1/credential-types/*`  
**Components:** CredentialsList, CredentialForm, CredentialTypeSelector, OAuthConnect

---

## 🔗 API Endpoints

### Credentials CRUD

#### 1. List Credentials
```http
GET /api/v1/workspaces/{workspace}/credentials
Authorization: Bearer {token}

Query Parameters:
- type (optional): Filter by credential type
- search (optional): Search by name

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "name": "OpenAI API Key",
      "type": "openai",
      "is_oauth": false,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "used_by_workflows_count": 3
    },
    {
      "id": "uuid",
      "name": "Google Sheets OAuth",
      "type": "google_sheets",
      "is_oauth": true,
      "oauth_status": "connected",
      "created_at": "2024-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "total": 2
  }
}
```

#### 2. Create Credential (API Key)
```http
POST /api/v1/workspaces/{workspace}/credentials
Content-Type: application/json

{
  "name": "My OpenAI Key",
  "type": "openai",
  "data": {
    "api_key": "sk-..."
  }
}

Response (201):
{
  "data": {
    "id": "uuid",
    "name": "My OpenAI Key",
    "type": "openai",
    "is_oauth": false
  }
}
```

#### 3. Get Credential Details
```http
GET /api/v1/workspaces/{workspace}/credentials/{id}

Response (200):
{
  "data": {
    "id": "uuid",
    "name": "My OpenAI Key",
    "type": "openai",
    "is_oauth": false,
    "data": {
      "api_key": "sk-..." // Masked or full depending on permissions
    },
    "created_at": "2024-01-01T00:00:00Z"
  }
}
```

#### 4. Update Credential
```http
PUT /api/v1/workspaces/{workspace}/credentials/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "data": {
    "api_key": "sk-new-key"
  }
}
```

#### 5. Delete Credential
```http
DELETE /api/v1/workspaces/{workspace}/credentials/{id}

Response (204): No Content
```

#### 6. Test Credential
```http
POST /api/v1/workspaces/{workspace}/credentials/{id}/test

Response (200):
{
  "success": true,
  "message": "Connection successful",
  "details": {
    "account_name": "John Doe",
    "quota_remaining": 1000
  }
}

Response (422) - Failed:
{
  "success": false,
  "message": "Invalid API key",
  "error": "Authentication failed"
}
```

### OAuth Flow

#### 7. Initiate OAuth Flow
```http
POST /api/v1/workspaces/{workspace}/oauth/initiate
Content-Type: application/json

{
  "credential_type": "google_sheets",
  "name": "My Google Account"
}

Response (200):
{
  "authorization_url": "https://accounts.google.com/o/oauth2/auth?...",
  "state": "random-state-token"
}
```

**Frontend Flow:**
1. Call initiate endpoint
2. Store `state` in localStorage
3. Open `authorization_url` in popup or redirect
4. User authorizes on provider site
5. Provider redirects to: `GET /api/v1/oauth/callback?code=...&state=...`
6. Backend handles callback, saves credential, redirects to success page

#### 8. OAuth Callback (handled by backend)
```http
GET /api/v1/oauth/callback
Query Parameters:
- code: Authorization code from provider
- state: State token for validation

Backend Response:
- Redirects to: {FRONTEND_URL}/credentials?oauth=success&credential_id=uuid
```

### Credential Types Catalog

#### 9. List Available Credential Types
```http
GET /api/v1/credential-types

Response (200):
{
  "data": [
    {
      "id": "openai",
      "name": "OpenAI",
      "description": "OpenAI API for GPT models",
      "is_oauth": false,
      "icon": "https://...",
      "fields": [
        {
          "key": "api_key",
          "label": "API Key",
          "type": "password",
          "required": true,
          "placeholder": "sk-..."
        }
      ],
      "documentation_url": "https://platform.openai.com/api-keys"
    },
    {
      "id": "google_sheets",
      "name": "Google Sheets",
      "is_oauth": true,
      "scopes": ["https://www.googleapis.com/auth/spreadsheets"]
    }
  ]
}
```

#### 10. Get Credential Type Details
```http
GET /api/v1/credential-types/{type}

Response (200):
{
  "data": {
    "id": "stripe",
    "name": "Stripe",
    "fields": [
      {
        "key": "secret_key",
        "label": "Secret Key",
        "type": "password"
      },
      {
        "key": "publishable_key",
        "label": "Publishable Key",
        "type": "text"
      }
    ]
  }
}
```

---

## 🗄️ State Management

### API Client
```javascript
// src/api/credentials.js
import apiClient from './client';

export const credentialsApi = {
  // List credentials
  list: (workspaceId, filters = {}) => 
    apiClient.get(`/workspaces/${workspaceId}/credentials`, { params: filters }),

  // Get single credential
  get: (workspaceId, credentialId) => 
    apiClient.get(`/workspaces/${workspaceId}/credentials/${credentialId}`),

  // Create credential
  create: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/credentials`, data),

  // Update credential
  update: (workspaceId, credentialId, data) => 
    apiClient.put(`/workspaces/${workspaceId}/credentials/${credentialId}`, data),

  // Delete credential
  delete: (workspaceId, credentialId) => 
    apiClient.delete(`/workspaces/${workspaceId}/credentials/${credentialId}`),

  // Test credential
  test: (workspaceId, credentialId) => 
    apiClient.post(`/workspaces/${workspaceId}/credentials/${credentialId}/test`),

  // OAuth initiate
  initiateOAuth: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/oauth/initiate`, data),
};

export const credentialTypesApi = {
  list: () => apiClient.get('/credential-types'),
  get: (typeId) => apiClient.get(`/credential-types/${typeId}`),
};
```

### React Query Hooks
```javascript
// src/hooks/useCredentials.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { credentialsApi, credentialTypesApi } from '../api/credentials';
import { useWorkspaceStore } from '../stores/workspaceStore';

// List credentials
export function useCredentials(filters = {}) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['credentials', workspaceId, filters],
    queryFn: () => credentialsApi.list(workspaceId, filters),
    enabled: !!workspaceId,
  });
}

// Get single credential
export function useCredential(credentialId) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['credentials', workspaceId, credentialId],
    queryFn: () => credentialsApi.get(workspaceId, credentialId),
    enabled: !!workspaceId && !!credentialId,
  });
}

// Create credential
export function useCreateCredential() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (data) => credentialsApi.create(workspaceId, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['credentials', workspaceId]);
    },
  });
}

// Update credential
export function useUpdateCredential() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: ({ credentialId, data }) => 
      credentialsApi.update(workspaceId, credentialId, data),
    onSuccess: (_, { credentialId }) => {
      queryClient.invalidateQueries(['credentials', workspaceId]);
      queryClient.invalidateQueries(['credentials', workspaceId, credentialId]);
    },
  });
}

// Delete credential
export function useDeleteCredential() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (credentialId) => credentialsApi.delete(workspaceId, credentialId),
    onSuccess: () => {
      queryClient.invalidateQueries(['credentials', workspaceId]);
    },
  });
}

// Test credential
export function useTestCredential() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (credentialId) => credentialsApi.test(workspaceId, credentialId),
  });
}

// List credential types
export function useCredentialTypes() {
  return useQuery({
    queryKey: ['credential-types'],
    queryFn: () => credentialTypesApi.list(),
    staleTime: 10 * 60 * 1000, // Cache for 10 minutes
  });
}
```

---

## 🎨 UI Components

### Credentials List Page
```javascript
// src/pages/Credentials.jsx
import { useState } from 'react';
import { useCredentials, useDeleteCredential } from '../hooks/useCredentials';
import CredentialCard from '../components/CredentialCard';
import CreateCredentialModal from '../components/CreateCredentialModal';

export default function CredentialsPage() {
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const { data: credentials, isLoading } = useCredentials();
  const deleteCredential = useDeleteCredential();

  const handleDelete = async (credentialId) => {
    if (confirm('Delete this credential? Workflows using it will fail.')) {
      await deleteCredential.mutateAsync(credentialId);
    }
  };

  if (isLoading) return <div>Loading...</div>;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Credentials</h1>
        <button
          onClick={() => setIsCreateModalOpen(true)}
          className="px-4 py-2 bg-blue-600 text-white rounded"
        >
          Add Credential
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {credentials?.data?.map((credential) => (
          <CredentialCard
            key={credential.id}
            credential={credential}
            onDelete={handleDelete}
          />
        ))}
      </div>

      {credentials?.data?.length === 0 && (
        <div className="text-center py-12 text-gray-500">
          No credentials yet. Add one to connect your workflows to external services.
        </div>
      )}

      <CreateCredentialModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
      />
    </div>
  );
}
```

### Credential Card Component
```javascript
// src/components/CredentialCard.jsx
import { useTestCredential } from '../hooks/useCredentials';
import { CheckCircle, XCircle } from 'lucide-react';

export default function CredentialCard({ credential, onDelete }) {
  const testCredential = useTestCredential();

  const handleTest = async () => {
    try {
      const result = await testCredential.mutateAsync(credential.id);
      if (result.data.success) {
        alert('Connection successful!');
      } else {
        alert('Connection failed: ' + result.data.message);
      }
    } catch (error) {
      alert('Test failed');
    }
  };

  return (
    <div className="border rounded-lg p-4 hover:shadow-md transition">
      <div className="flex items-start justify-between">
        <div>
          <h3 className="font-semibold">{credential.name}</h3>
          <p className="text-sm text-gray-600">{credential.type}</p>
        </div>
        {credential.is_oauth && credential.oauth_status === 'connected' && (
          <CheckCircle className="w-5 h-5 text-green-500" />
        )}
      </div>

      <div className="mt-4 text-sm text-gray-600">
        Used by {credential.used_by_workflows_count} workflows
      </div>

      <div className="mt-4 flex gap-2">
        <button
          onClick={handleTest}
          disabled={testCredential.isPending}
          className="text-sm px-3 py-1 border rounded hover:bg-gray-50"
        >
          {testCredential.isPending ? 'Testing...' : 'Test'}
        </button>
        <button
          onClick={() => onDelete(credential.id)}
          className="text-sm px-3 py-1 text-red-600 border border-red-200 rounded hover:bg-red-50"
        >
          Delete
        </button>
      </div>
    </div>
  );
}
```

### Create Credential Modal
```javascript
// src/components/CreateCredentialModal.jsx
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useCredentialTypes, useCreateCredential } from '../hooks/useCredentials';

export default function CreateCredentialModal({ isOpen, onClose }) {
  const [selectedType, setSelectedType] = useState(null);
  const { data: types } = useCredentialTypes();
  const createCredential = useCreateCredential();
  const { register, handleSubmit, reset } = useForm();

  const onSubmit = async (formData) => {
    const payload = {
      name: formData.name,
      type: selectedType.id,
      data: {},
    };

    // Build data object from form fields
    selectedType.fields.forEach(field => {
      payload.data[field.key] = formData[field.key];
    });

    await createCredential.mutateAsync(payload);
    reset();
    setSelectedType(null);
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 className="text-xl font-bold mb-4">Add Credential</h2>

        {!selectedType ? (
          <div>
            <p className="text-sm text-gray-600 mb-4">Select a service:</p>
            <div className="space-y-2 max-h-96 overflow-y-auto">
              {types?.data?.map((type) => (
                <button
                  key={type.id}
                  onClick={() => setSelectedType(type)}
                  className="w-full text-left p-3 border rounded hover:bg-gray-50"
                >
                  <div className="font-medium">{type.name}</div>
                  <div className="text-sm text-gray-600">{type.description}</div>
                  {type.is_oauth && (
                    <span className="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded mt-1 inline-block">
                      OAuth
                    </span>
                  )}
                </button>
              ))}
            </div>
          </div>
        ) : (
          <form onSubmit={handleSubmit(onSubmit)}>
            <div className="mb-4">
              <label className="block text-sm font-medium mb-1">Name</label>
              <input
                {...register('name', { required: true })}
                className="w-full border rounded px-3 py-2"
                placeholder="My API Key"
              />
            </div>

            {selectedType.fields?.map((field) => (
              <div key={field.key} className="mb-4">
                <label className="block text-sm font-medium mb-1">
                  {field.label}
                  {field.required && <span className="text-red-500">*</span>}
                </label>
                <input
                  {...register(field.key, { required: field.required })}
                  type={field.type || 'text'}
                  className="w-full border rounded px-3 py-2"
                  placeholder={field.placeholder}
                />
              </div>
            ))}

            <div className="flex gap-2 mt-6">
              <button
                type="submit"
                disabled={createCredential.isPending}
                className="flex-1 px-4 py-2 bg-blue-600 text-white rounded"
              >
                {createCredential.isPending ? 'Saving...' : 'Save'}
              </button>
              <button
                type="button"
                onClick={() => {
                  setSelectedType(null);
                  onClose();
                }}
                className="px-4 py-2 border rounded"
              >
                Cancel
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
```

### OAuth Connection Flow
```javascript
// src/components/OAuthConnect.jsx
import { credentialsApi } from '../api/credentials';
import { useWorkspaceStore } from '../stores/workspaceStore';

export default function OAuthConnect({ credentialType, onSuccess }) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  const handleConnect = async () => {
    try {
      const response = await credentialsApi.initiateOAuth(workspaceId, {
        credential_type: credentialType.id,
        name: `${credentialType.name} Connection`,
      });

      // Store state for verification
      localStorage.setItem('oauth_state', response.data.state);

      // Open OAuth popup
      const width = 600;
      const height = 700;
      const left = (window.innerWidth - width) / 2;
      const top = (window.innerHeight - height) / 2;

      const popup = window.open(
        response.data.authorization_url,
        'OAuth Login',
        `width=${width},height=${height},left=${left},top=${top}`
      );

      // Listen for OAuth completion
      const checkPopup = setInterval(() => {
        if (popup.closed) {
          clearInterval(checkPopup);
          // Check if OAuth was successful
          const urlParams = new URLSearchParams(window.location.search);
          if (urlParams.get('oauth') === 'success') {
            onSuccess?.(urlParams.get('credential_id'));
          }
        }
      }, 500);
    } catch (error) {
      console.error('OAuth initiation failed:', error);
      alert('Failed to start OAuth flow');
    }
  };

  return (
    <button
      onClick={handleConnect}
      className="px-4 py-2 bg-blue-600 text-white rounded flex items-center gap-2"
    >
      <span>Connect {credentialType.name}</span>
    </button>
  );
}
```

---

## 💡 Common Use Cases

### 1. Filter Credentials by Type
```javascript
const { data: openaiCredentials } = useCredentials({ type: 'openai' });
```

### 2. Select Credential in Workflow Node
```javascript
function NodeCredentialSelector({ nodeId, onChange }) {
  const { data: credentials } = useCredentials();
  
  return (
    <select onChange={(e) => onChange(e.target.value)}>
      <option value="">Select credential...</option>
      {credentials?.data?.map(cred => (
        <option key={cred.id} value={cred.id}>{cred.name}</option>
      ))}
    </select>
  );
}
```

### 3. Validate Credential Before Workflow Execution
```javascript
const testCredential = useTestCredential();

const validateBeforeRun = async (credentialId) => {
  const result = await testCredential.mutateAsync(credentialId);
  if (!result.data.success) {
    throw new Error('Invalid credential: ' + result.data.message);
  }
};
```

### 4. Handle OAuth Callback on Success Page
```javascript
// src/pages/OAuthSuccess.jsx
import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';

export default function OAuthSuccess() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  useEffect(() => {
    const credentialId = searchParams.get('credential_id');
    if (credentialId) {
      // Show success message
      setTimeout(() => {
        navigate('/credentials');
      }, 2000);
    }
  }, []);

  return (
    <div className="flex items-center justify-center min-h-screen">
      <div className="text-center">
        <h1 className="text-2xl font-bold text-green-600">✓ Connected Successfully</h1>
        <p className="text-gray-600 mt-2">Redirecting...</p>
      </div>
    </div>
  );
}
```

---

## 🔒 Security Best Practices

1. **Never log credential data** in console or error messages
2. **Use HTTPS only** for all credential-related requests
3. **Mask sensitive fields** in UI (show only last 4 characters)
4. **Implement credential rotation** - allow users to update keys
5. **Test connections** before saving to validate credentials
6. **Scope OAuth properly** - request minimal necessary permissions
7. **Handle OAuth state validation** to prevent CSRF attacks

---

## 🎯 Next Steps

- Read [Module 7: Variables & Tags](./07-variables-tags.md)
- Implement credential selection in workflow editor
- Add credential health monitoring dashboard
