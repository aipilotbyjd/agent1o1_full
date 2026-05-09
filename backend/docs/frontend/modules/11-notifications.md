# 🔔 Module 11: Notifications

**In-app notifications, preferences, and multi-channel delivery**

**APIs:** `/api/v1/notifications/*`, `/api/v1/notification-preferences/*`, `/api/v1/notification-channels/*`  
**Components:** NotificationBell, NotificationList, NotificationPreferences, ChannelConfig

---

## 🔗 API Endpoints

### User Notifications

#### 1. List Notifications
```http
GET /api/v1/notifications
Authorization: Bearer {token}

Query Parameters:
- unread_only (optional): true/false
- type (optional): workflow_execution, team_invite, system
- page (optional): Pagination

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "type": "workflow_execution_failed",
      "title": "Workflow execution failed",
      "message": "Workflow 'User Onboarding' failed at Email node",
      "data": {
        "workflow_id": "uuid",
        "execution_id": "uuid",
        "error": "SMTP connection failed"
      },
      "is_read": false,
      "created_at": "2024-01-15T10:30:00Z"
    },
    {
      "id": "uuid",
      "type": "team_member_joined",
      "title": "New team member",
      "message": "Jane Smith joined your workspace",
      "is_read": true,
      "created_at": "2024-01-14T15:20:00Z"
    }
  ],
  "meta": {
    "total": 42,
    "unread_count": 3
  }
}
```

#### 2. Get Unread Count
```http
GET /api/v1/notifications/unread-count

Response (200):
{
  "count": 3
}
```

#### 3. Mark Notification as Read
```http
POST /api/v1/notifications/{id}/read

Response (200):
{
  "message": "Notification marked as read"
}
```

#### 4. Mark All as Read
```http
POST /api/v1/notifications/read-all

Response (200):
{
  "message": "All notifications marked as read"
}
```

#### 5. Delete Notification
```http
DELETE /api/v1/notifications/{id}

Response (204): No Content
```

#### 6. Delete All Notifications
```http
DELETE /api/v1/notifications

Response (204): No Content
```

### Notification Preferences

#### 7. Get Preferences
```http
GET /api/v1/notification-preferences

Response (200):
{
  "data": {
    "workflow_execution_success": {
      "in_app": true,
      "email": false,
      "slack": false
    },
    "workflow_execution_failed": {
      "in_app": true,
      "email": true,
      "slack": true
    },
    "team_member_joined": {
      "in_app": true,
      "email": false
    },
    "workspace_credit_low": {
      "in_app": true,
      "email": true
    }
  }
}
```

#### 8. Update Preferences
```http
PUT /api/v1/notification-preferences
Content-Type: application/json

{
  "workflow_execution_failed": {
    "in_app": true,
    "email": true,
    "slack": true
  }
}

Response (200):
{
  "message": "Preferences updated successfully"
}
```

### Notification Channels (Slack, Discord, Webhook, SMS)

#### 9. List Notification Channels
```http
GET /api/v1/notification-channels

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "type": "slack",
      "name": "Engineering Channel",
      "config": {
        "webhook_url": "https://hooks.slack.com/...",
        "channel": "#engineering"
      },
      "is_active": true,
      "created_at": "2024-01-01T00:00:00Z"
    },
    {
      "id": "uuid",
      "type": "discord",
      "name": "Team Alerts",
      "is_active": true
    }
  ]
}
```

#### 10. Create Notification Channel
```http
POST /api/v1/notification-channels
Content-Type: application/json

{
  "type": "slack",
  "name": "Engineering Channel",
  "config": {
    "webhook_url": "https://hooks.slack.com/services/...",
    "channel": "#engineering"
  }
}

Response (201):
{
  "data": {
    "id": "uuid",
    "type": "slack",
    "name": "Engineering Channel"
  }
}
```

#### 11. Update Notification Channel
```http
PUT /api/v1/notification-channels/{id}
Content-Type: application/json

{
  "name": "Updated Channel Name",
  "is_active": false
}
```

#### 12. Delete Notification Channel
```http
DELETE /api/v1/notification-channels/{id}

Response (204): No Content
```

#### 13. Test Notification Channel
```http
POST /api/v1/notification-channels/{id}/test

Response (200):
{
  "success": true,
  "message": "Test notification sent successfully"
}

Response (422) - Failed:
{
  "success": false,
  "error": "Invalid webhook URL"
}
```

---

## 🗄️ State Management

### API Client
```javascript
// src/api/notifications.js
import apiClient from './client';

export const notificationsApi = {
  list: (params = {}) => 
    apiClient.get('/notifications', { params }),
  
  unreadCount: () => 
    apiClient.get('/notifications/unread-count'),
  
  markRead: (notificationId) => 
    apiClient.post(`/notifications/${notificationId}/read`),
  
  markAllRead: () => 
    apiClient.post('/notifications/read-all'),
  
  delete: (notificationId) => 
    apiClient.delete(`/notifications/${notificationId}`),
  
  deleteAll: () => 
    apiClient.delete('/notifications'),
  
  getPreferences: () => 
    apiClient.get('/notification-preferences'),
  
  updatePreferences: (preferences) => 
    apiClient.put('/notification-preferences', preferences),
  
  listChannels: () => 
    apiClient.get('/notification-channels'),
  
  createChannel: (data) => 
    apiClient.post('/notification-channels', data),
  
  updateChannel: (channelId, data) => 
    apiClient.put(`/notification-channels/${channelId}`, data),
  
  deleteChannel: (channelId) => 
    apiClient.delete(`/notification-channels/${channelId}`),
  
  testChannel: (channelId) => 
    apiClient.post(`/notification-channels/${channelId}/test`),
};
```

### React Query Hooks
```javascript
// src/hooks/useNotifications.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { notificationsApi } from '../api/notifications';

export function useNotifications(params = {}) {
  return useQuery({
    queryKey: ['notifications', params],
    queryFn: () => notificationsApi.list(params),
    refetchInterval: 30000, // Refetch every 30 seconds
  });
}

export function useUnreadCount() {
  return useQuery({
    queryKey: ['notifications-unread-count'],
    queryFn: () => notificationsApi.unreadCount(),
    refetchInterval: 15000, // Refetch every 15 seconds
  });
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (notificationId) => notificationsApi.markRead(notificationId),
    onSuccess: () => {
      queryClient.invalidateQueries(['notifications']);
      queryClient.invalidateQueries(['notifications-unread-count']);
    },
  });
}

export function useMarkAllRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => notificationsApi.markAllRead(),
    onSuccess: () => {
      queryClient.invalidateQueries(['notifications']);
      queryClient.invalidateQueries(['notifications-unread-count']);
    },
  });
}

export function useDeleteNotification() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (notificationId) => notificationsApi.delete(notificationId),
    onSuccess: () => {
      queryClient.invalidateQueries(['notifications']);
      queryClient.invalidateQueries(['notifications-unread-count']);
    },
  });
}

export function useNotificationPreferences() {
  return useQuery({
    queryKey: ['notification-preferences'],
    queryFn: () => notificationsApi.getPreferences(),
  });
}

export function useUpdatePreferences() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (preferences) => notificationsApi.updatePreferences(preferences),
    onSuccess: () => {
      queryClient.invalidateQueries(['notification-preferences']);
    },
  });
}

export function useNotificationChannels() {
  return useQuery({
    queryKey: ['notification-channels'],
    queryFn: () => notificationsApi.listChannels(),
  });
}

export function useCreateChannel() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data) => notificationsApi.createChannel(data),
    onSuccess: () => {
      queryClient.invalidateQueries(['notification-channels']);
    },
  });
}

export function useTestChannel() {
  return useMutation({
    mutationFn: (channelId) => notificationsApi.testChannel(channelId),
  });
}
```

---

## 🎨 UI Components

### Notification Bell (Header)
```javascript
// src/components/NotificationBell.jsx
import { useState } from 'react';
import { Bell } from 'lucide-react';
import { useUnreadCount, useNotifications } from '../hooks/useNotifications';
import NotificationDropdown from './NotificationDropdown';

export default function NotificationBell() {
  const [isOpen, setIsOpen] = useState(false);
  const { data: unreadData } = useUnreadCount();
  const unreadCount = unreadData?.data?.count || 0;

  return (
    <div className="relative">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="relative p-2 text-gray-600 hover:text-gray-900"
      >
        <Bell className="w-6 h-6" />
        {unreadCount > 0 && (
          <span className="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        )}
      </button>

      {isOpen && (
        <NotificationDropdown onClose={() => setIsOpen(false)} />
      )}
    </div>
  );
}
```

### Notification Dropdown
```javascript
// src/components/NotificationDropdown.jsx
import { useNotifications, useMarkNotificationRead, useMarkAllRead } from '../hooks/useNotifications';
import { formatDistanceToNow } from 'date-fns';
import { CheckCheck, X } from 'lucide-react';
import { Link } from 'react-router-dom';

export default function NotificationDropdown({ onClose }) {
  const { data: notifications } = useNotifications({ unread_only: false });
  const markRead = useMarkNotificationRead();
  const markAllRead = useMarkAllRead();

  const handleNotificationClick = async (notification) => {
    if (!notification.is_read) {
      await markRead.mutateAsync(notification.id);
    }
    onClose();
  };

  return (
    <>
      {/* Backdrop */}
      <div
        className="fixed inset-0 z-40"
        onClick={onClose}
      />

      {/* Dropdown */}
      <div className="absolute right-0 mt-2 w-96 bg-white border rounded-lg shadow-xl z-50 max-h-96 overflow-hidden flex flex-col">
        {/* Header */}
        <div className="p-4 border-b flex justify-between items-center">
          <h3 className="font-semibold">Notifications</h3>
          <button
            onClick={() => markAllRead.mutate()}
            className="text-sm text-blue-600 hover:text-blue-700 flex items-center gap-1"
          >
            <CheckCheck className="w-4 h-4" />
            Mark all read
          </button>
        </div>

        {/* List */}
        <div className="overflow-y-auto flex-1">
          {notifications?.data?.length === 0 ? (
            <div className="p-8 text-center text-gray-500">
              No notifications
            </div>
          ) : (
            notifications?.data?.map((notification) => (
              <button
                key={notification.id}
                onClick={() => handleNotificationClick(notification)}
                className={`w-full text-left p-4 border-b hover:bg-gray-50 transition ${
                  !notification.is_read ? 'bg-blue-50' : ''
                }`}
              >
                <div className="flex items-start gap-3">
                  {!notification.is_read && (
                    <div className="w-2 h-2 bg-blue-500 rounded-full mt-2" />
                  )}
                  <div className="flex-1 min-w-0">
                    <h4 className="font-medium text-sm">{notification.title}</h4>
                    <p className="text-sm text-gray-600 mt-1 line-clamp-2">
                      {notification.message}
                    </p>
                    <p className="text-xs text-gray-500 mt-2">
                      {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
                    </p>
                  </div>
                </div>
              </button>
            ))
          )}
        </div>

        {/* Footer */}
        <div className="p-3 border-t bg-gray-50">
          <Link
            to="/notifications"
            onClick={onClose}
            className="text-sm text-blue-600 hover:text-blue-700 text-center block"
          >
            View all notifications
          </Link>
        </div>
      </div>
    </>
  );
}
```

### Notification Preferences Page
```javascript
// src/pages/NotificationPreferences.jsx
import { useNotificationPreferences, useUpdatePreferences } from '../hooks/useNotifications';
import { useState } from 'react';

const NOTIFICATION_TYPES = [
  {
    key: 'workflow_execution_success',
    label: 'Workflow Execution Success',
    description: 'When a workflow completes successfully',
  },
  {
    key: 'workflow_execution_failed',
    label: 'Workflow Execution Failed',
    description: 'When a workflow execution fails',
  },
  {
    key: 'team_member_joined',
    label: 'Team Member Joined',
    description: 'When someone joins your workspace',
  },
  {
    key: 'workspace_credit_low',
    label: 'Credits Running Low',
    description: 'When workspace credits are below threshold',
  },
];

const CHANNELS = ['in_app', 'email', 'slack'];

export default function NotificationPreferences() {
  const { data: preferences, isLoading } = useNotificationPreferences();
  const updatePreferences = useUpdatePreferences();
  const [localPrefs, setLocalPrefs] = useState(preferences?.data || {});

  const handleToggle = (type, channel) => {
    const updated = {
      ...localPrefs,
      [type]: {
        ...localPrefs[type],
        [channel]: !localPrefs[type]?.[channel],
      },
    };
    setLocalPrefs(updated);
  };

  const handleSave = async () => {
    await updatePreferences.mutateAsync(localPrefs);
  };

  if (isLoading) return <div>Loading...</div>;

  return (
    <div className="p-6 max-w-4xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Notification Preferences</h1>
        <p className="text-gray-600 text-sm mt-1">
          Choose how you want to be notified
        </p>
      </div>

      <div className="bg-white rounded-lg shadow overflow-hidden">
        <table className="w-full">
          <thead className="bg-gray-50 border-b">
            <tr>
              <th className="px-6 py-3 text-left text-sm font-semibold">Event</th>
              <th className="px-6 py-3 text-center text-sm font-semibold">In-App</th>
              <th className="px-6 py-3 text-center text-sm font-semibold">Email</th>
              <th className="px-6 py-3 text-center text-sm font-semibold">Slack</th>
            </tr>
          </thead>
          <tbody>
            {NOTIFICATION_TYPES.map((type) => (
              <tr key={type.key} className="border-b">
                <td className="px-6 py-4">
                  <div className="font-medium">{type.label}</div>
                  <div className="text-sm text-gray-600">{type.description}</div>
                </td>
                {CHANNELS.map((channel) => (
                  <td key={channel} className="px-6 py-4 text-center">
                    <input
                      type="checkbox"
                      checked={localPrefs[type.key]?.[channel] || false}
                      onChange={() => handleToggle(type.key, channel)}
                      className="w-5 h-5 rounded"
                    />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="mt-6">
        <button
          onClick={handleSave}
          disabled={updatePreferences.isPending}
          className="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
        >
          {updatePreferences.isPending ? 'Saving...' : 'Save Preferences'}
        </button>
      </div>
    </div>
  );
}
```

---

## 💡 Common Use Cases

### 1. Real-time Notification Updates (WebSocket/SSE)
```javascript
import { useQueryClient } from '@tanstack/react-query';

function useNotificationUpdates() {
  const queryClient = useQueryClient();

  useEffect(() => {
    // Connect to SSE or WebSocket
    const eventSource = new EventSource('/api/v1/notifications/stream');

    eventSource.onmessage = (event) => {
      const notification = JSON.parse(event.data);
      
      // Invalidate queries to refetch
      queryClient.invalidateQueries(['notifications']);
      queryClient.invalidateQueries(['notifications-unread-count']);
    };

    return () => eventSource.close();
  }, []);
}
```

### 2. Navigate to Related Resource
```javascript
const handleNotificationClick = (notification) => {
  if (notification.data?.workflow_id) {
    navigate(`/workflows/${notification.data.workflow_id}`);
  } else if (notification.data?.execution_id) {
    navigate(`/executions/${notification.data.execution_id}`);
  }
};
```

---

## 🎯 Next Steps

- Read [Module 12: Settings](./12-settings.md)
- Implement push notifications (browser API)
- Add notification sound preferences
