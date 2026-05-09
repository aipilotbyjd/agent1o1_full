# 👥 Module 10: Team Management

**Invite members, manage roles, and collaborate on workflows**

**APIs:** `/api/v1/workspaces/{workspace}/members/*`, `/api/v1/workspaces/{workspace}/invitations/*`  
**Components:** TeamList, InviteMemberModal, RoleSelector, MemberCard

---

## 🔗 API Endpoints

### Workspace Members

#### 1. List Members
```http
GET /api/v1/workspaces/{workspace}/members
Authorization: Bearer {token}

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "name": "John Doe",
      "email": "john@example.com",
      "avatar": "https://...",
      "role": "owner",
      "joined_at": "2024-01-01T00:00:00Z",
      "last_active_at": "2024-01-15T10:30:00Z",
      "workflows_created": 12,
      "executions_run": 540
    },
    {
      "id": "uuid",
      "user_id": "uuid",
      "name": "Jane Smith",
      "email": "jane@example.com",
      "role": "editor",
      "joined_at": "2024-01-05T00:00:00Z"
    }
  ]
}
```

#### 2. Update Member Role
```http
PUT /api/v1/workspaces/{workspace}/members/{user_id}
Content-Type: application/json

{
  "role": "viewer"
}

Roles:
- owner: Full control
- admin: Manage workspace, members, workflows
- editor: Create and edit workflows
- viewer: View-only access

Response (200):
{
  "data": {
    "id": "uuid",
    "role": "viewer"
  }
}
```

#### 3. Remove Member
```http
DELETE /api/v1/workspaces/{workspace}/members/{user_id}

Response (204): No Content
```

#### 4. Transfer Ownership
```http
POST /api/v1/workspaces/{workspace}/transfer-ownership
Content-Type: application/json

{
  "new_owner_id": "uuid"
}

Response (200):
{
  "message": "Ownership transferred successfully"
}
```

#### 5. Leave Workspace
```http
POST /api/v1/workspaces/{workspace}/leave

Response (200):
{
  "message": "You have left the workspace"
}
```

### Invitations

#### 6. List Pending Invitations
```http
GET /api/v1/workspaces/{workspace}/invitations

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "email": "newuser@example.com",
      "role": "editor",
      "invited_by": "John Doe",
      "status": "pending",
      "expires_at": "2024-01-22T00:00:00Z",
      "created_at": "2024-01-15T00:00:00Z"
    }
  ]
}
```

#### 7. Send Invitation
```http
POST /api/v1/workspaces/{workspace}/invitations
Content-Type: application/json

{
  "email": "newuser@example.com",
  "role": "editor",
  "message": "Join our workflow workspace!"
}

Response (201):
{
  "data": {
    "id": "uuid",
    "email": "newuser@example.com",
    "status": "pending",
    "invitation_link": "https://app.example.com/invite/abc123"
  }
}
```

#### 8. Cancel Invitation
```http
DELETE /api/v1/workspaces/{workspace}/invitations/{invitation_id}

Response (204): No Content
```

#### 9. Accept Invitation
```http
POST /api/v1/invitations/{token}/accept
Authorization: Bearer {token}

Response (200):
{
  "data": {
    "workspace_id": "uuid",
    "workspace_name": "Marketing Team",
    "role": "editor"
  }
}
```

#### 10. Decline Invitation
```http
POST /api/v1/invitations/{token}/decline
Authorization: Bearer {token}

Response (200):
{
  "message": "Invitation declined"
}
```

---

## 🗄️ State Management

### API Client
```javascript
// src/api/team.js
import apiClient from './client';

export const teamApi = {
  // Members
  listMembers: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/members`),
  
  updateMemberRole: (workspaceId, userId, role) => 
    apiClient.put(`/workspaces/${workspaceId}/members/${userId}`, { role }),
  
  removeMember: (workspaceId, userId) => 
    apiClient.delete(`/workspaces/${workspaceId}/members/${userId}`),
  
  transferOwnership: (workspaceId, newOwnerId) => 
    apiClient.post(`/workspaces/${workspaceId}/transfer-ownership`, {
      new_owner_id: newOwnerId,
    }),
  
  leaveWorkspace: (workspaceId) => 
    apiClient.post(`/workspaces/${workspaceId}/leave`),
  
  // Invitations
  listInvitations: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/invitations`),
  
  sendInvitation: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/invitations`, data),
  
  cancelInvitation: (workspaceId, invitationId) => 
    apiClient.delete(`/workspaces/${workspaceId}/invitations/${invitationId}`),
  
  acceptInvitation: (token) => 
    apiClient.post(`/invitations/${token}/accept`),
  
  declineInvitation: (token) => 
    apiClient.post(`/invitations/${token}/decline`),
};
```

### React Query Hooks
```javascript
// src/hooks/useTeam.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { teamApi } from '../api/team';
import { useWorkspaceStore } from '../stores/workspaceStore';

export function useMembers() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['members', workspaceId],
    queryFn: () => teamApi.listMembers(workspaceId),
    enabled: !!workspaceId,
  });
}

export function useUpdateMemberRole() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: ({ userId, role }) => 
      teamApi.updateMemberRole(workspaceId, userId, role),
    onSuccess: () => {
      queryClient.invalidateQueries(['members', workspaceId]);
    },
  });
}

export function useRemoveMember() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (userId) => teamApi.removeMember(workspaceId, userId),
    onSuccess: () => {
      queryClient.invalidateQueries(['members', workspaceId]);
    },
  });
}

export function useTransferOwnership() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (newOwnerId) => 
      teamApi.transferOwnership(workspaceId, newOwnerId),
    onSuccess: () => {
      queryClient.invalidateQueries(['members', workspaceId]);
      queryClient.invalidateQueries(['workspaces', workspaceId]);
    },
  });
}

export function useInvitations() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['invitations', workspaceId],
    queryFn: () => teamApi.listInvitations(workspaceId),
    enabled: !!workspaceId,
  });
}

export function useSendInvitation() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (data) => teamApi.sendInvitation(workspaceId, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['invitations', workspaceId]);
    },
  });
}

export function useCancelInvitation() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (invitationId) => 
      teamApi.cancelInvitation(workspaceId, invitationId),
    onSuccess: () => {
      queryClient.invalidateQueries(['invitations', workspaceId]);
    },
  });
}

export function useInvitationActions() {
  const queryClient = useQueryClient();

  const accept = useMutation({
    mutationFn: (token) => teamApi.acceptInvitation(token),
    onSuccess: () => {
      queryClient.invalidateQueries(['workspaces']);
    },
  });

  const decline = useMutation({
    mutationFn: (token) => teamApi.declineInvitation(token),
  });

  return { accept, decline };
}
```

---

## 🎨 UI Components

### Team Page
```javascript
// src/pages/Team.jsx
import { useState } from 'react';
import { useMembers, useInvitations } from '../hooks/useTeam';
import MemberCard from '../components/MemberCard';
import InviteMemberModal from '../components/InviteMemberModal';
import PendingInvitationCard from '../components/PendingInvitationCard';
import { UserPlus } from 'lucide-react';

export default function TeamPage() {
  const [isInviteModalOpen, setIsInviteModalOpen] = useState(false);
  const { data: members, isLoading: membersLoading } = useMembers();
  const { data: invitations } = useInvitations();

  if (membersLoading) return <div>Loading...</div>;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold">Team</h1>
          <p className="text-gray-600 text-sm mt-1">
            Manage workspace members and permissions
          </p>
        </div>
        <button
          onClick={() => setIsInviteModalOpen(true)}
          className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded"
        >
          <UserPlus className="w-4 h-4" />
          Invite Member
        </button>
      </div>

      {/* Pending Invitations */}
      {invitations?.data?.length > 0 && (
        <div className="mb-8">
          <h2 className="text-lg font-semibold mb-3">Pending Invitations</h2>
          <div className="space-y-2">
            {invitations.data.map((invitation) => (
              <PendingInvitationCard key={invitation.id} invitation={invitation} />
            ))}
          </div>
        </div>
      )}

      {/* Members */}
      <div>
        <h2 className="text-lg font-semibold mb-3">
          Members ({members?.data?.length})
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {members?.data?.map((member) => (
            <MemberCard key={member.id} member={member} />
          ))}
        </div>
      </div>

      <InviteMemberModal
        isOpen={isInviteModalOpen}
        onClose={() => setIsInviteModalOpen(false)}
      />
    </div>
  );
}
```

### Member Card Component
```javascript
// src/components/MemberCard.jsx
import { useState } from 'react';
import { useUpdateMemberRole, useRemoveMember } from '../hooks/useTeam';
import { useAuthStore } from '../stores/authStore';
import { MoreVertical, Crown } from 'lucide-react';

const ROLES = [
  { value: 'owner', label: 'Owner', color: 'purple' },
  { value: 'admin', label: 'Admin', color: 'blue' },
  { value: 'editor', label: 'Editor', color: 'green' },
  { value: 'viewer', label: 'Viewer', color: 'gray' },
];

export default function MemberCard({ member }) {
  const [showMenu, setShowMenu] = useState(false);
  const currentUser = useAuthStore((state) => state.user);
  const updateRole = useUpdateMemberRole();
  const removeMember = useRemoveMember();

  const isCurrentUser = currentUser?.id === member.user_id;
  const canManage = !isCurrentUser && member.role !== 'owner';

  const roleColor = ROLES.find(r => r.value === member.role)?.color || 'gray';

  const handleRoleChange = async (newRole) => {
    if (confirm(`Change ${member.name}'s role to ${newRole}?`)) {
      await updateRole.mutateAsync({
        userId: member.user_id,
        role: newRole,
      });
    }
    setShowMenu(false);
  };

  const handleRemove = async () => {
    if (confirm(`Remove ${member.name} from workspace?`)) {
      await removeMember.mutateAsync(member.user_id);
    }
    setShowMenu(false);
  };

  return (
    <div className="bg-white border rounded-lg p-4 relative">
      {canManage && (
        <div className="absolute top-4 right-4">
          <button
            onClick={() => setShowMenu(!showMenu)}
            className="text-gray-400 hover:text-gray-600"
          >
            <MoreVertical className="w-5 h-5" />
          </button>
          {showMenu && (
            <div className="absolute right-0 mt-2 w-48 bg-white border rounded-lg shadow-lg z-10">
              <div className="py-1">
                <div className="px-3 py-2 text-xs font-semibold text-gray-500">Change Role</div>
                {ROLES.filter(r => r.value !== 'owner').map((role) => (
                  <button
                    key={role.value}
                    onClick={() => handleRoleChange(role.value)}
                    className="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm"
                  >
                    {role.label}
                  </button>
                ))}
                <hr className="my-1" />
                <button
                  onClick={handleRemove}
                  className="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm text-red-600"
                >
                  Remove Member
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      <div className="flex items-start gap-3">
        <div className="w-12 h-12 bg-gray-200 rounded-full overflow-hidden">
          {member.avatar ? (
            <img src={member.avatar} alt={member.name} className="w-full h-full object-cover" />
          ) : (
            <div className="w-full h-full flex items-center justify-center bg-blue-500 text-white font-semibold">
              {member.name.charAt(0).toUpperCase()}
            </div>
          )}
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <h3 className="font-semibold truncate">{member.name}</h3>
            {member.role === 'owner' && (
              <Crown className="w-4 h-4 text-yellow-500" />
            )}
          </div>
          <p className="text-sm text-gray-600 truncate">{member.email}</p>
          <span className={`inline-block mt-2 px-2 py-1 rounded text-xs bg-${roleColor}-100 text-${roleColor}-700`}>
            {ROLES.find(r => r.value === member.role)?.label}
          </span>
        </div>
      </div>

      {member.workflows_created !== undefined && (
        <div className="mt-4 pt-4 border-t grid grid-cols-2 gap-4 text-sm">
          <div>
            <div className="text-gray-600">Workflows</div>
            <div className="font-semibold">{member.workflows_created}</div>
          </div>
          <div>
            <div className="text-gray-600">Executions</div>
            <div className="font-semibold">{member.executions_run}</div>
          </div>
        </div>
      )}
    </div>
  );
}
```

### Invite Member Modal
```javascript
// src/components/InviteMemberModal.jsx
import { useForm } from 'react-hook-form';
import { useSendInvitation } from '../hooks/useTeam';
import { X } from 'lucide-react';

export default function InviteMemberModal({ isOpen, onClose }) {
  const sendInvitation = useSendInvitation();
  const { register, handleSubmit, reset, formState: { errors } } = useForm({
    defaultValues: {
      email: '',
      role: 'editor',
      message: '',
    },
  });

  const onSubmit = async (data) => {
    await sendInvitation.mutateAsync(data);
    reset();
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-md">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-bold">Invite Team Member</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">Email Address *</label>
            <input
              {...register('email', {
                required: 'Email is required',
                pattern: {
                  value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                  message: 'Invalid email address',
                },
              })}
              type="email"
              className="w-full border rounded px-3 py-2"
              placeholder="colleague@example.com"
            />
            {errors.email && (
              <p className="text-red-600 text-sm mt-1">{errors.email.message}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">Role *</label>
            <select
              {...register('role')}
              className="w-full border rounded px-3 py-2"
            >
              <option value="admin">Admin - Manage workspace and members</option>
              <option value="editor">Editor - Create and edit workflows</option>
              <option value="viewer">Viewer - View-only access</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">Personal Message (optional)</label>
            <textarea
              {...register('message')}
              className="w-full border rounded px-3 py-2"
              rows={3}
              placeholder="Join our team!"
            />
          </div>

          <div className="flex gap-2 pt-2">
            <button
              type="submit"
              disabled={sendInvitation.isPending}
              className="flex-1 px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50"
            >
              {sendInvitation.isPending ? 'Sending...' : 'Send Invitation'}
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

### Pending Invitation Card
```javascript
// src/components/PendingInvitationCard.jsx
import { useCancelInvitation } from '../hooks/useTeam';
import { Mail, X, Clock } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

export default function PendingInvitationCard({ invitation }) {
  const cancelInvitation = useCancelInvitation();

  const handleCancel = async () => {
    if (confirm('Cancel this invitation?')) {
      await cancelInvitation.mutateAsync(invitation.id);
    }
  };

  return (
    <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-center justify-between">
      <div className="flex items-center gap-3">
        <Mail className="w-5 h-5 text-yellow-600" />
        <div>
          <div className="font-medium">{invitation.email}</div>
          <div className="text-sm text-gray-600">
            Invited as {invitation.role} by {invitation.invited_by}
            {' • '}
            <Clock className="w-3 h-3 inline" />
            {' '}{formatDistanceToNow(new Date(invitation.created_at), { addSuffix: true })}
          </div>
        </div>
      </div>
      <button
        onClick={handleCancel}
        className="text-gray-400 hover:text-red-600"
      >
        <X className="w-5 h-5" />
      </button>
    </div>
  );
}
```

### Accept Invitation Page
```javascript
// src/pages/AcceptInvitation.jsx
import { useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useInvitationActions } from '../hooks/useTeam';
import { CheckCircle, XCircle } from 'lucide-react';

export default function AcceptInvitation() {
  const { token } = useParams();
  const navigate = useNavigate();
  const { accept, decline } = useInvitationActions();

  const handleAccept = async () => {
    try {
      const result = await accept.mutateAsync(token);
      setTimeout(() => {
        navigate(`/workspaces/${result.data.workspace_id}`);
      }, 2000);
    } catch (error) {
      alert('Failed to accept invitation');
    }
  };

  const handleDecline = async () => {
    if (confirm('Decline this invitation?')) {
      await decline.mutateAsync(token);
      navigate('/');
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="bg-white p-8 rounded-lg shadow-lg max-w-md w-full text-center">
        <h1 className="text-2xl font-bold mb-4">Workspace Invitation</h1>
        <p className="text-gray-600 mb-6">
          You've been invited to join a workspace. Accept to start collaborating!
        </p>

        <div className="flex gap-3">
          <button
            onClick={handleAccept}
            disabled={accept.isPending}
            className="flex-1 flex items-center justify-center gap-2 px-4 py-3 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
          >
            <CheckCircle className="w-5 h-5" />
            {accept.isPending ? 'Accepting...' : 'Accept'}
          </button>
          <button
            onClick={handleDecline}
            disabled={decline.isPending}
            className="flex-1 flex items-center justify-center gap-2 px-4 py-3 border border-red-600 text-red-600 rounded hover:bg-red-50"
          >
            <XCircle className="w-5 h-5" />
            Decline
          </button>
        </div>

        {accept.isSuccess && (
          <div className="mt-4 text-green-600">
            ✓ Invitation accepted! Redirecting...
          </div>
        )}
      </div>
    </div>
  );
}
```

---

## 💡 Common Use Cases

### 1. Bulk Invite
```javascript
const sendInvitation = useSendInvitation();

const bulkInvite = async (emails, role) => {
  for (const email of emails) {
    await sendInvitation.mutateAsync({ email, role });
  }
};
```

### 2. Role-based UI
```javascript
import { useWorkspaceStore } from '../stores/workspaceStore';

function FeatureButton() {
  const currentRole = useWorkspaceStore((state) => state.currentWorkspace?.role);
  
  if (!['owner', 'admin'].includes(currentRole)) {
    return null; // Hide for viewers/editors
  }
  
  return <button>Admin Feature</button>;
}
```

---

## 🔒 Permission Levels

| Feature | Owner | Admin | Editor | Viewer |
|---------|-------|-------|--------|--------|
| View workflows | ✓ | ✓ | ✓ | ✓ |
| Create workflows | ✓ | ✓ | ✓ | ✗ |
| Edit workflows | ✓ | ✓ | ✓ | ✗ |
| Delete workflows | ✓ | ✓ | ✓ | ✗ |
| Manage credentials | ✓ | ✓ | ✓ | ✗ |
| Invite members | ✓ | ✓ | ✗ | ✗ |
| Manage roles | ✓ | ✓ | ✗ | ✗ |
| Workspace settings | ✓ | ✓ | ✗ | ✗ |
| Delete workspace | ✓ | ✗ | ✗ | ✗ |
| Transfer ownership | ✓ | ✗ | ✗ | ✗ |

---

## 🎯 Next Steps

- Read [Module 11: Notifications](./11-notifications.md)
- Implement team activity feed
- Add member search and filtering
