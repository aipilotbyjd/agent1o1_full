# 🔐 Module 1: Authentication & User Profile

**API Endpoints:** `/api/v1/auth/*` and `/api/v1/user/*`  
**Components:** Login, Register, ForgotPassword, Profile, Settings

---

## 📋 Table of Contents

1. [API Endpoints](#api-endpoints)
2. [State Management](#state-management)
3. [Components](#components)
4. [Implementation](#implementation)
5. [Code Examples](#code-examples)

---

## 🔗 API Endpoints

### Public (No Auth Required)

#### 1. Register
```http
POST /api/v1/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!"
}

Response (201):
{
  "user": {
    "id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": null,
    "created_at": "2024-01-01T00:00:00.000000Z"
  },
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbG...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

#### 2. Login
```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "SecurePass123!"
}

Response (200):
{
  "user": {...},
  "access_token": "...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

#### 3. Forgot Password
```http
POST /api/v1/auth/forgot-password
Content-Type: application/json

{
  "email": "john@example.com"
}

Response (200):
{
  "message": "Password reset link sent to your email"
}
```

#### 4. Reset Password
```http
POST /api/v1/auth/reset-password
Content-Type: application/json

{
  "email": "john@example.com",
  "token": "reset-token-from-email",
  "password": "NewSecurePass123!",
  "password_confirmation": "NewSecurePass123!"
}

Response (200):
{
  "message": "Password reset successful"
}
```

### Protected (Require Auth Token)

#### 5. Get Current User
```http
GET /api/v1/user
Authorization: Bearer {access_token}

Response (200):
{
  "data": {
    "id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2024-01-01T00:00:00.000000Z",
    "avatar_url": "https://...",
    "created_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

#### 6. Update Profile
```http
PUT /api/v1/user
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "name": "John Updated",
  "email": "john.updated@example.com"
}

Response (200):
{
  "data": {
    "id": "uuid",
    "name": "John Updated",
    ...
  }
}
```

#### 7. Change Password
```http
PUT /api/v1/user/password
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "current_password": "OldPass123!",
  "password": "NewPass123!",
  "password_confirmation": "NewPass123!"
}

Response (200):
{
  "message": "Password changed successfully"
}
```

#### 8. Upload Avatar
```http
POST /api/v1/user/avatar
Authorization: Bearer {access_token}
Content-Type: multipart/form-data

avatar: (file)

Response (200):
{
  "data": {
    "avatar_url": "https://storage.../avatar.jpg"
  }
}
```

#### 9. Delete Avatar
```http
DELETE /api/v1/user/avatar
Authorization: Bearer {access_token}

Response (204): No Content
```

#### 10. Logout
```http
POST /api/v1/auth/logout
Authorization: Bearer {access_token}

Response (200):
{
  "message": "Successfully logged out"
}
```

#### 11. Refresh Token
```http
POST /api/v1/auth/refresh
Authorization: Bearer {access_token}

Response (200):
{
  "access_token": "new-token...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

---

## 🗄️ State Management

### Auth Store (Zustand)
```javascript
// src/stores/authStore.js
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export const useAuthStore = create(
  persist(
    (set, get) => ({
      // State
      user: null,
      token: null,
      isAuthenticated: false,

      // Actions
      setAuth: (user, token) => set({ 
        user, 
        token, 
        isAuthenticated: true 
      }),

      updateUser: (userData) => set((state) => ({
        user: { ...state.user, ...userData }
      })),

      logout: () => {
        localStorage.removeItem('access_token');
        set({ user: null, token: null, isAuthenticated: false });
      },

      // Helpers
      getToken: () => get().token,
      getUser: () => get().user,
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({ 
        user: state.user, 
        token: state.token,
        isAuthenticated: state.isAuthenticated 
      }),
    }
  )
);
```

---

## 🎨 Components

### 1. Login Component
```jsx
// src/pages/Login.jsx
import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';
import { authApi } from '../api/auth';

export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  
  const setAuth = useAuthStore((state) => state.setAuth);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const response = await authApi.login({ email, password });
      
      // Store token
      localStorage.setItem('access_token', response.access_token);
      
      // Update auth state
      setAuth(response.user, response.access_token);
      
      // Redirect to dashboard
      navigate('/dashboard');
    } catch (err) {
      setError(err.response?.data?.message || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow">
        <div>
          <h2 className="text-3xl font-bold text-center">Sign in</h2>
        </div>
        
        <form onSubmit={handleSubmit} className="mt-8 space-y-6">
          {error && (
            <div className="bg-red-50 text-red-600 p-3 rounded">
              {error}
            </div>
          )}
          
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Email
            </label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">
              Password
            </label>
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"
            />
          </div>

          <div className="flex items-center justify-between">
            <Link 
              to="/forgot-password" 
              className="text-sm text-blue-600 hover:text-blue-500"
            >
              Forgot password?
            </Link>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50"
          >
            {loading ? 'Signing in...' : 'Sign in'}
          </button>

          <div className="text-center text-sm">
            Don't have an account?{' '}
            <Link to="/register" className="text-blue-600 hover:text-blue-500">
              Sign up
            </Link>
          </div>
        </form>
      </div>
    </div>
  );
}
```

### 2. Register Component
```jsx
// src/pages/Register.jsx
import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';
import { authApi } from '../api/auth';

export default function Register() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(false);
  
  const setAuth = useAuthStore((state) => state.setAuth);
  const navigate = useNavigate();

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
    // Clear error for this field
    setErrors({ ...errors, [e.target.name]: '' });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});
    setLoading(true);

    try {
      const response = await authApi.register(formData);
      localStorage.setItem('access_token', response.access_token);
      setAuth(response.user, response.access_token);
      navigate('/dashboard');
    } catch (err) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors);
      } else {
        setErrors({ general: err.response?.data?.message || 'Registration failed' });
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow">
        <h2 className="text-3xl font-bold text-center">Create Account</h2>
        
        <form onSubmit={handleSubmit} className="mt-8 space-y-6">
          {errors.general && (
            <div className="bg-red-50 text-red-600 p-3 rounded">
              {errors.general}
            </div>
          )}
          
          <div>
            <label className="block text-sm font-medium text-gray-700">Name</label>
            <input
              type="text"
              name="name"
              required
              value={formData.name}
              onChange={handleChange}
              className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"
            />
            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name[0]}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">Email</label>
            <input
              type="email"
              name="email"
              required
              value={formData.email}
              onChange={handleChange}
              className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"
            />
            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email[0]}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">Password</label>
            <input
              type="password"
              name="password"
              required
              value={formData.password}
              onChange={handleChange}
              className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"
            />
            {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password[0]}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input
              type="password"
              name="password_confirmation"
              required
              value={formData.password_confirmation}
              onChange={handleChange}
              className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50"
          >
            {loading ? 'Creating account...' : 'Sign up'}
          </button>

          <div className="text-center text-sm">
            Already have an account?{' '}
            <Link to="/login" className="text-blue-600 hover:text-blue-500">
              Sign in
            </Link>
          </div>
        </form>
      </div>
    </div>
  );
}
```

### 3. Profile Component
```jsx
// src/pages/Profile.jsx
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useAuthStore } from '../stores/authStore';
import { userApi } from '../api/user';

export default function Profile() {
  const user = useAuthStore((state) => state.user);
  const updateUser = useAuthStore((state) => state.updateUser);
  const queryClient = useQueryClient();

  const [formData, setFormData] = useState({
    name: user?.name || '',
    email: user?.email || '',
  });

  const updateMutation = useMutation({
    mutationFn: userApi.update,
    onSuccess: (data) => {
      updateUser(data.data);
      queryClient.invalidateQueries(['user']);
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    updateMutation.mutate(formData);
  };

  return (
    <div className="max-w-2xl mx-auto p-6">
      <h1 className="text-2xl font-bold mb-6">Profile Settings</h1>

      <form onSubmit={handleSubmit} className="space-y-6">
        <div>
          <label className="block text-sm font-medium text-gray-700">Name</label>
          <input
            type="text"
            value={formData.name}
            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700">Email</label>
          <input
            type="email"
            value={formData.email}
            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"
          />
        </div>

        <button
          type="submit"
          disabled={updateMutation.isPending}
          className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
        >
          {updateMutation.isPending ? 'Saving...' : 'Save Changes'}
        </button>

        {updateMutation.isSuccess && (
          <p className="text-green-600">Profile updated successfully!</p>
        )}
        {updateMutation.isError && (
          <p className="text-red-600">Failed to update profile</p>
        )}
      </form>
    </div>
  );
}
```

---

## 📁 API Module

```javascript
// src/api/auth.js
import apiClient from './client';

export const authApi = {
  register: (data) => 
    apiClient.post('/auth/register', data).then(res => res.data),
  
  login: (data) => 
    apiClient.post('/auth/login', data).then(res => res.data),
  
  logout: () => 
    apiClient.post('/auth/logout').then(res => res.data),
  
  refresh: () => 
    apiClient.post('/auth/refresh').then(res => res.data),
  
  forgotPassword: (email) => 
    apiClient.post('/auth/forgot-password', { email }).then(res => res.data),
  
  resetPassword: (data) => 
    apiClient.post('/auth/reset-password', data).then(res => res.data),
};

// src/api/user.js
import apiClient from './client';

export const userApi = {
  me: () => 
    apiClient.get('/user').then(res => res.data),
  
  update: (data) => 
    apiClient.put('/user', data).then(res => res.data),
  
  changePassword: (data) => 
    apiClient.put('/user/password', data).then(res => res.data),
  
  uploadAvatar: (file) => {
    const formData = new FormData();
    formData.append('avatar', file);
    return apiClient.post('/user/avatar', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    }).then(res => res.data);
  },
  
  deleteAvatar: () => 
    apiClient.delete('/user/avatar').then(res => res.data),
};
```

---

## 🛡️ Protected Route Component

```jsx
// src/components/ProtectedRoute.jsx
import { Navigate } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';

export default function ProtectedRoute({ children }) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  
  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }
  
  return children;
}

// Usage in App.jsx
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import ProtectedRoute from './components/ProtectedRoute';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        
        <Route path="/dashboard" element={
          <ProtectedRoute>
            <Dashboard />
          </ProtectedRoute>
        } />
        
        {/* More protected routes... */}
      </Routes>
    </BrowserRouter>
  );
}
```

---

## ✅ Checklist

- [ ] Setup auth store with Zustand
- [ ] Create API client with interceptors
- [ ] Implement Login component
- [ ] Implement Register component
- [ ] Implement Protected Route wrapper
- [ ] Implement Profile page
- [ ] Add avatar upload functionality
- [ ] Add password change form
- [ ] Handle token refresh automatically
- [ ] Add loading states
- [ ] Add error handling
- [ ] Add form validation

---

**Next Module:** [Workspace Management →](./02-workspace-management.md)
