# ⚙️ Module 12: Settings

**User settings, workspace settings, and billing management**

**APIs:** `/api/v1/user/*`, `/api/v1/workspaces/{workspace}/settings/*`, `/api/v1/workspaces/{workspace}/billing/*`  
**Components:** UserSettings, WorkspaceSettings, BillingDashboard, ProfileEditor

---

## 🔗 API Endpoints

### User Profile Settings

#### 1. Get User Profile
```http
GET /api/v1/user
Authorization: Bearer {token}

Response (200):
{
  "data": {
    "id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "avatar": "https://...",
    "timezone": "America/New_York",
    "locale": "en",
    "email_verified_at": "2024-01-01T00:00:00Z",
    "created_at": "2024-01-01T00:00:00Z"
  }
}
```

#### 2. Update User Profile
```http
PUT /api/v1/user
Content-Type: application/json

{
  "name": "John Smith",
  "timezone": "UTC",
  "locale": "en"
}

Response (200):
{
  "data": {
    "id": "uuid",
    "name": "John Smith",
    "timezone": "UTC"
  }
}
```

#### 3. Change Password
```http
PUT /api/v1/user/password
Content-Type: application/json

{
  "current_password": "old_password",
  "new_password": "new_password",
  "new_password_confirmation": "new_password"
}

Response (200):
{
  "message": "Password changed successfully"
}
```

#### 4. Upload Avatar
```http
POST /api/v1/user/avatar
Content-Type: multipart/form-data

Form Data:
- avatar: [file]

Response (200):
{
  "data": {
    "avatar": "https://storage.example.com/avatars/user-uuid.jpg"
  }
}
```

#### 5. Delete Avatar
```http
DELETE /api/v1/user/avatar

Response (200):
{
  "message": "Avatar deleted successfully"
}
```

#### 6. Delete Account
```http
DELETE /api/v1/user
Content-Type: application/json

{
  "password": "current_password",
  "confirmation": "DELETE MY ACCOUNT"
}

Response (204): No Content
```

### Workspace Settings

#### 7. Get Workspace Settings
```http
GET /api/v1/workspaces/{workspace}/settings

Response (200):
{
  "data": {
    "timezone": "America/New_York",
    "default_workflow_timeout": 3600,
    "execution_retention_days": 30,
    "allow_public_workflows": false,
    "require_2fa": false,
    "ip_whitelist": [],
    "custom_domain": null,
    "branding": {
      "logo": "https://...",
      "primary_color": "#3b82f6"
    }
  }
}
```

#### 8. Update Workspace Settings
```http
PUT /api/v1/workspaces/{workspace}/settings
Content-Type: application/json

{
  "timezone": "UTC",
  "default_workflow_timeout": 7200,
  "execution_retention_days": 60,
  "require_2fa": true
}

Response (200):
{
  "message": "Settings updated successfully"
}
```

### Billing & Credits

#### 9. Get Credit Balance
```http
GET /api/v1/workspaces/{workspace}/credits/balance

Response (200):
{
  "data": {
    "balance": 15420,
    "currency": "credits",
    "last_refill": "2024-01-10T00:00:00Z",
    "monthly_usage": 8500,
    "estimated_days_remaining": 18
  }
}
```

#### 10. Get Credit Transactions
```http
GET /api/v1/workspaces/{workspace}/credits/transactions

Query Parameters:
- type (optional): purchase, usage, refund
- page (optional)

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "type": "purchase",
      "amount": 10000,
      "description": "Credit purchase - 10,000 credits",
      "created_at": "2024-01-10T00:00:00Z"
    },
    {
      "id": "uuid",
      "type": "usage",
      "amount": -50,
      "description": "Workflow execution - User Onboarding",
      "execution_id": "uuid",
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "total": 150,
    "current_page": 1
  }
}
```

#### 11. Create Checkout Session (Buy Credits)
```http
POST /api/v1/workspaces/{workspace}/billing/checkout
Content-Type: application/json

{
  "package": "starter", // starter, pro, enterprise
  "credits": 10000,
  "success_url": "https://app.example.com/billing/success",
  "cancel_url": "https://app.example.com/billing/cancel"
}

Response (200):
{
  "checkout_url": "https://checkout.stripe.com/...",
  "session_id": "cs_..."
}
```

#### 12. Buy Credits Directly
```http
POST /api/v1/workspaces/{workspace}/billing/credits
Content-Type: application/json

{
  "amount": 10000,
  "payment_method_id": "pm_..." // Stripe payment method
}

Response (200):
{
  "data": {
    "transaction_id": "uuid",
    "amount": 10000,
    "new_balance": 25420
  }
}
```

#### 13. Access Billing Portal
```http
GET /api/v1/workspaces/{workspace}/billing/portal

Response (200):
{
  "url": "https://billing.stripe.com/p/session/..."
}
```

---

## 🗄️ State Management

### API Client
```javascript
// src/api/settings.js
import apiClient from './client';

export const settingsApi = {
  // User
  getUser: () => apiClient.get('/user'),
  updateUser: (data) => apiClient.put('/user', data),
  changePassword: (data) => apiClient.put('/user/password', data),
  uploadAvatar: (file) => {
    const formData = new FormData();
    formData.append('avatar', file);
    return apiClient.post('/user/avatar', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
  deleteAvatar: () => apiClient.delete('/user/avatar'),
  deleteAccount: (data) => apiClient.delete('/user', { data }),
  
  // Workspace Settings
  getWorkspaceSettings: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/settings`),
  updateWorkspaceSettings: (workspaceId, data) => 
    apiClient.put(`/workspaces/${workspaceId}/settings`, data),
  
  // Billing
  getCredits: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/credits/balance`),
  getTransactions: (workspaceId, params = {}) => 
    apiClient.get(`/workspaces/${workspaceId}/credits/transactions`, { params }),
  createCheckout: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/billing/checkout`, data),
  buyCredits: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/billing/credits`, data),
  getBillingPortal: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/billing/portal`),
};
```

### React Query Hooks
```javascript
// src/hooks/useSettings.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { settingsApi } from '../api/settings';
import { useWorkspaceStore } from '../stores/workspaceStore';

export function useUser() {
  return useQuery({
    queryKey: ['user'],
    queryFn: () => settingsApi.getUser(),
    staleTime: 5 * 60 * 1000,
  });
}

export function useUpdateUser() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data) => settingsApi.updateUser(data),
    onSuccess: () => {
      queryClient.invalidateQueries(['user']);
    },
  });
}

export function useChangePassword() {
  return useMutation({
    mutationFn: (data) => settingsApi.changePassword(data),
  });
}

export function useUploadAvatar() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (file) => settingsApi.uploadAvatar(file),
    onSuccess: () => {
      queryClient.invalidateQueries(['user']);
    },
  });
}

export function useWorkspaceSettings() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['workspace-settings', workspaceId],
    queryFn: () => settingsApi.getWorkspaceSettings(workspaceId),
    enabled: !!workspaceId,
  });
}

export function useUpdateWorkspaceSettings() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (data) => settingsApi.updateWorkspaceSettings(workspaceId, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['workspace-settings', workspaceId]);
    },
  });
}

export function useCredits() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['credits', workspaceId],
    queryFn: () => settingsApi.getCredits(workspaceId),
    enabled: !!workspaceId,
    refetchInterval: 60000, // Refetch every minute
  });
}

export function useTransactions(params = {}) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['transactions', workspaceId, params],
    queryFn: () => settingsApi.getTransactions(workspaceId, params),
    enabled: !!workspaceId,
  });
}

export function useCreateCheckout() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (data) => settingsApi.createCheckout(workspaceId, data),
  });
}
```

---

## 🎨 UI Components

### User Settings Page
```javascript
// src/pages/UserSettings.jsx
import { useState } from 'react';
import { useUser, useUpdateUser, useChangePassword, useUploadAvatar } from '../hooks/useSettings';
import { useForm } from 'react-hook-form';
import { Camera } from 'lucide-react';

export default function UserSettings() {
  const { data: user } = useUser();
  const updateUser = useUpdateUser();
  const changePassword = useChangePassword();
  const uploadAvatar = useUploadAvatar();
  
  const { register, handleSubmit } = useForm({
    values: user?.data,
  });

  const { register: registerPassword, handleSubmit: handlePasswordSubmit, reset: resetPassword } = useForm();

  const onProfileSubmit = async (data) => {
    await updateUser.mutateAsync(data);
  };

  const onPasswordSubmit = async (data) => {
    await changePassword.mutateAsync(data);
    resetPassword();
    alert('Password changed successfully');
  };

  const handleAvatarChange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadAvatar.mutateAsync(file);
    }
  };

  return (
    <div className="p-6 max-w-3xl mx-auto">
      <h1 className="text-2xl font-bold mb-6">User Settings</h1>

      {/* Avatar */}
      <div className="bg-white rounded-lg shadow p-6 mb-6">
        <h2 className="text-lg font-semibold mb-4">Profile Picture</h2>
        <div className="flex items-center gap-4">
          <div className="relative">
            <div className="w-24 h-24 bg-gray-200 rounded-full overflow-hidden">
              {user?.data?.avatar ? (
                <img src={user.data.avatar} alt="Avatar" className="w-full h-full object-cover" />
              ) : (
                <div className="w-full h-full flex items-center justify-center bg-blue-500 text-white text-3xl font-semibold">
                  {user?.data?.name?.charAt(0).toUpperCase()}
                </div>
              )}
            </div>
            <label className="absolute bottom-0 right-0 w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center cursor-pointer hover:bg-blue-700">
              <Camera className="w-4 h-4 text-white" />
              <input
                type="file"
                accept="image/*"
                onChange={handleAvatarChange}
                className="hidden"
              />
            </label>
          </div>
          {user?.data?.avatar && (
            <button className="text-sm text-red-600 hover:text-red-700">
              Remove Photo
            </button>
          )}
        </div>
      </div>

      {/* Profile Info */}
      <div className="bg-white rounded-lg shadow p-6 mb-6">
        <h2 className="text-lg font-semibold mb-4">Profile Information</h2>
        <form onSubmit={handleSubmit(onProfileSubmit)} className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">Name</label>
            <input
              {...register('name')}
              className="w-full border rounded px-3 py-2"
            />
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">Email</label>
            <input
              {...register('email')}
              type="email"
              disabled
              className="w-full border rounded px-3 py-2 bg-gray-100"
            />
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">Timezone</label>
            <select
              {...register('timezone')}
              className="w-full border rounded px-3 py-2"
            >
              <option value="UTC">UTC</option>
              <option value="America/New_York">Eastern Time</option>
              <option value="America/Chicago">Central Time</option>
              <option value="America/Los_Angeles">Pacific Time</option>
            </select>
          </div>

          <button
            type="submit"
            disabled={updateUser.isPending}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
          >
            {updateUser.isPending ? 'Saving...' : 'Save Changes'}
          </button>
        </form>
      </div>

      {/* Change Password */}
      <div className="bg-white rounded-lg shadow p-6">
        <h2 className="text-lg font-semibold mb-4">Change Password</h2>
        <form onSubmit={handlePasswordSubmit(onPasswordSubmit)} className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">Current Password</label>
            <input
              {...registerPassword('current_password', { required: true })}
              type="password"
              className="w-full border rounded px-3 py-2"
            />
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">New Password</label>
            <input
              {...registerPassword('new_password', { required: true, minLength: 8 })}
              type="password"
              className="w-full border rounded px-3 py-2"
            />
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">Confirm New Password</label>
            <input
              {...registerPassword('new_password_confirmation', { required: true })}
              type="password"
              className="w-full border rounded px-3 py-2"
            />
          </div>

          <button
            type="submit"
            disabled={changePassword.isPending}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
          >
            {changePassword.isPending ? 'Changing...' : 'Change Password'}
          </button>
        </form>
      </div>
    </div>
  );
}
```

### Billing Dashboard
```javascript
// src/pages/Billing.jsx
import { useCredits, useTransactions, useCreateCheckout } from '../hooks/useSettings';
import { CreditCard, TrendingUp, TrendingDown } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

const CREDIT_PACKAGES = [
  { id: 'starter', credits: 10000, price: 49, popular: false },
  { id: 'pro', credits: 50000, price: 199, popular: true },
  { id: 'enterprise', credits: 200000, price: 699, popular: false },
];

export default function Billing() {
  const { data: credits } = useCredits();
  const { data: transactions } = useTransactions();
  const createCheckout = useCreateCheckout();

  const handleBuyCredits = async (pkg) => {
    const result = await createCheckout.mutateAsync({
      package: pkg.id,
      credits: pkg.credits,
      success_url: window.location.origin + '/billing/success',
      cancel_url: window.location.origin + '/billing',
    });
    window.location.href = result.data.checkout_url;
  };

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-6">Billing & Credits</h1>

      {/* Credit Balance */}
      <div className="bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg p-6 mb-6">
        <div className="flex justify-between items-start">
          <div>
            <p className="text-blue-100 text-sm">Current Balance</p>
            <h2 className="text-4xl font-bold mt-1">
              {credits?.data?.balance?.toLocaleString()} credits
            </h2>
            <p className="text-blue-100 text-sm mt-2">
              Estimated {credits?.data?.estimated_days_remaining} days remaining
            </p>
          </div>
          <CreditCard className="w-12 h-12 text-blue-200" />
        </div>
      </div>

      {/* Credit Packages */}
      <div className="mb-8">
        <h2 className="text-lg font-semibold mb-4">Buy Credits</h2>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {CREDIT_PACKAGES.map((pkg) => (
            <div
              key={pkg.id}
              className={`border rounded-lg p-6 relative ${
                pkg.popular ? 'ring-2 ring-blue-500' : ''
              }`}
            >
              {pkg.popular && (
                <span className="absolute top-0 right-4 -translate-y-1/2 bg-blue-500 text-white text-xs px-3 py-1 rounded-full">
                  Popular
                </span>
              )}
              <h3 className="font-semibold text-lg capitalize">{pkg.id}</h3>
              <div className="mt-2">
                <span className="text-3xl font-bold">${pkg.price}</span>
              </div>
              <p className="text-gray-600 mt-1">
                {pkg.credits.toLocaleString()} credits
              </p>
              <button
                onClick={() => handleBuyCredits(pkg)}
                disabled={createCheckout.isPending}
                className="w-full mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
              >
                Purchase
              </button>
            </div>
          ))}
        </div>
      </div>

      {/* Transaction History */}
      <div>
        <h2 className="text-lg font-semibold mb-4">Transaction History</h2>
        <div className="bg-white rounded-lg shadow">
          {transactions?.data?.map((transaction) => (
            <div
              key={transaction.id}
              className="p-4 border-b last:border-b-0 flex items-center justify-between"
            >
              <div className="flex items-center gap-3">
                {transaction.type === 'purchase' ? (
                  <TrendingUp className="w-5 h-5 text-green-600" />
                ) : (
                  <TrendingDown className="w-5 h-5 text-gray-600" />
                )}
                <div>
                  <p className="font-medium">{transaction.description}</p>
                  <p className="text-sm text-gray-600">
                    {formatDistanceToNow(new Date(transaction.created_at), { addSuffix: true })}
                  </p>
                </div>
              </div>
              <span
                className={`font-mono font-semibold ${
                  transaction.amount > 0 ? 'text-green-600' : 'text-gray-600'
                }`}
              >
                {transaction.amount > 0 ? '+' : ''}{transaction.amount.toLocaleString()}
              </span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
```

---

## 💡 Common Use Cases

### 1. Low Credit Warning
```javascript
function CreditWarning() {
  const { data: credits } = useCredits();
  
  if (credits?.data?.balance < 1000) {
    return (
      <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4">
        <p className="text-yellow-700">
          Your credit balance is low. <a href="/billing" className="underline">Buy more credits</a>
        </p>
      </div>
    );
  }
  
  return null;
}
```

### 2. Feature Gating by Credits
```javascript
function ExecuteButton({ workflowId }) {
  const { data: credits } = useCredits();
  const canExecute = credits?.data?.balance > 0;
  
  return (
    <button disabled={!canExecute}>
      {canExecute ? 'Execute' : 'Insufficient Credits'}
    </button>
  );
}
```

---

## 🎯 Next Steps

- Read [Module 13: Analytics & Monitoring](./13-analytics.md)
- Implement 2FA settings
- Add usage analytics dashboard
