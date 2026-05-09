# 🤖 Module 14: AI Agents System

**Create, manage, and deploy AI-powered conversational agents with skills and triggers**

**APIs:** `/api/v1/workspaces/{workspace}/agents/*`, `/api/v1/workspaces/{workspace}/agent-skills/*`  
**Components:** AgentBuilder, AgentChat, SkillEditor, TriggerConfig

---

## 🔗 API Endpoints

### Agents Management

#### 1. List Agents
```http
GET /api/v1/workspaces/{workspace}/agents
Authorization: Bearer {token}

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "name": "Customer Support Bot",
      "description": "Answers common customer questions",
      "model": "gpt-4o",
      "system_prompt": "You are a helpful customer support agent...",
      "temperature": 0.7,
      "is_active": true,
      "skills_count": 3,
      "conversations_count": 152,
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### 2. Create Agent
```http
POST /api/v1/workspaces/{workspace}/agents
Content-Type: application/json

{
  "name": "Sales Assistant",
  "description": "Helps qualify leads and schedule demos",
  "model": "gpt-4o",
  "system_prompt": "You are a sales assistant...",
  "temperature": 0.7,
  "max_tokens": 1000
}

Response (201):
{
  "data": {
    "id": "uuid",
    "name": "Sales Assistant",
    "model": "gpt-4o"
  }
}
```

#### 3. Get Agent Details
```http
GET /api/v1/workspaces/{workspace}/agents/{id}

Response (200):
{
  "data": {
    "id": "uuid",
    "name": "Customer Support Bot",
    "model": "gpt-4o",
    "system_prompt": "You are helpful...",
    "temperature": 0.7,
    "skills": [
      {
        "id": "uuid",
        "name": "Order Status Lookup",
        "type": "api_call"
      }
    ],
    "triggers": [
      {
        "id": "uuid",
        "type": "webhook",
        "url": "https://..."
      }
    ]
  }
}
```

#### 4. Update Agent
```http
PUT /api/v1/workspaces/{workspace}/agents/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "temperature": 0.8,
  "is_active": false
}
```

#### 5. Delete Agent
```http
DELETE /api/v1/workspaces/{workspace}/agents/{id}

Response (204): No Content
```

#### 6. Duplicate Agent
```http
POST /api/v1/workspaces/{workspace}/agents/{id}/duplicate

Response (201):
{
  "data": {
    "id": "new-uuid",
    "name": "Customer Support Bot (Copy)"
  }
}
```

### Agent Skills

#### 7. List Agent Skills
```http
GET /api/v1/workspaces/{workspace}/agent-skills

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "name": "Order Status Lookup",
      "type": "api_call",
      "description": "Retrieves order status from database",
      "config": {
        "endpoint": "https://api.example.com/orders/{order_id}",
        "method": "GET"
      },
      "created_at": "2024-01-01T00:00:00Z"
    },
    {
      "id": "uuid",
      "name": "Knowledge Base Search",
      "type": "vector_search",
      "references_count": 45
    }
  ]
}
```

#### 8. Create Agent Skill
```http
POST /api/v1/workspaces/{workspace}/agent-skills
Content-Type: application/json

{
  "name": "Product Catalog Search",
  "type": "api_call",
  "description": "Search products in catalog",
  "config": {
    "endpoint": "https://api.example.com/products/search",
    "method": "POST",
    "headers": {
      "Authorization": "Bearer {{credential.api_key}}"
    }
  }
}

Skill Types:
- api_call: Call external APIs
- vector_search: RAG-based knowledge retrieval
- workflow: Trigger a workflow
- script: Execute custom code

Response (201):
{
  "data": {
    "id": "uuid",
    "name": "Product Catalog Search",
    "type": "api_call"
  }
}
```

#### 9. Attach Skill to Agent
```http
POST /api/v1/workspaces/{workspace}/agents/{agent_id}/skills/attach
Content-Type: application/json

{
  "skill_id": "uuid"
}

Response (200):
{
  "message": "Skill attached successfully"
}
```

#### 10. Detach Skill from Agent
```http
DELETE /api/v1/workspaces/{workspace}/agents/{agent_id}/skills/{skill_id}

Response (200):
{
  "message": "Skill detached successfully"
}
```

### Agent Conversations

#### 11. List Conversations
```http
GET /api/v1/workspaces/{workspace}/agents/{agent_id}/conversations

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "session_id": "session_abc123",
      "messages_count": 8,
      "started_at": "2024-01-15T10:00:00Z",
      "last_message_at": "2024-01-15T10:15:00Z"
    }
  ]
}
```

#### 12. Get Conversation Details
```http
GET /api/v1/workspaces/{workspace}/agents/{agent_id}/conversations/{conversation_id}

Response (200):
{
  "data": {
    "id": "uuid",
    "session_id": "session_abc123",
    "messages": [
      {
        "role": "user",
        "content": "What's the status of order #12345?",
        "created_at": "2024-01-15T10:00:00Z"
      },
      {
        "role": "assistant",
        "content": "Let me check that for you...",
        "skill_used": "Order Status Lookup",
        "created_at": "2024-01-15T10:00:05Z"
      }
    ]
  }
}
```

#### 13. Send Message to Agent
```http
POST /api/v1/workspaces/{workspace}/agents/{agent_id}/conversations/{conversation_id}/messages
Content-Type: application/json

{
  "message": "What products do you have in stock?",
  "context": {
    "user_id": "user_123"
  }
}

Response (200):
{
  "data": {
    "role": "assistant",
    "content": "We have several products in stock including...",
    "skills_used": ["Product Catalog Search"],
    "confidence": 0.95
  }
}
```

#### 14. Create Conversation
```http
POST /api/v1/workspaces/{workspace}/agents/{agent_id}/conversations
Content-Type: application/json

{
  "session_id": "unique_session_id",
  "metadata": {
    "user_id": "user_123",
    "channel": "web"
  }
}

Response (201):
{
  "data": {
    "id": "uuid",
    "session_id": "unique_session_id"
  }
}
```

### Agent Triggers

#### 15. List Agent Triggers
```http
GET /api/v1/workspaces/{workspace}/agents/{agent_id}/triggers

Response (200):
{
  "data": [
    {
      "id": "uuid",
      "type": "webhook",
      "config": {
        "url": "https://your-domain.com/api/v1/agent-webhook/abc123",
        "method": "POST"
      },
      "is_active": true
    }
  ]
}
```

#### 16. Create Agent Trigger
```http
POST /api/v1/workspaces/{workspace}/agents/{agent_id}/triggers
Content-Type: application/json

{
  "type": "webhook",
  "config": {
    "authentication": "bearer_token"
  }
}

Trigger Types:
- webhook: HTTP endpoint
- schedule: Cron-based
- event: Workflow event

Response (201):
{
  "data": {
    "id": "uuid",
    "type": "webhook",
    "url": "https://..."
  }
}
```

---

## 🗄️ State Management

### API Client
```javascript
// src/api/agents.js
import apiClient from './client';

export const agentsApi = {
  // Agents
  list: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/agents`),
  
  get: (workspaceId, agentId) => 
    apiClient.get(`/workspaces/${workspaceId}/agents/${agentId}`),
  
  create: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/agents`, data),
  
  update: (workspaceId, agentId, data) => 
    apiClient.put(`/workspaces/${workspaceId}/agents/${agentId}`, data),
  
  delete: (workspaceId, agentId) => 
    apiClient.delete(`/workspaces/${workspaceId}/agents/${agentId}`),
  
  duplicate: (workspaceId, agentId) => 
    apiClient.post(`/workspaces/${workspaceId}/agents/${agentId}/duplicate`),
  
  // Skills
  listSkills: (workspaceId) => 
    apiClient.get(`/workspaces/${workspaceId}/agent-skills`),
  
  createSkill: (workspaceId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/agent-skills`, data),
  
  attachSkill: (workspaceId, agentId, skillId) => 
    apiClient.post(`/workspaces/${workspaceId}/agents/${agentId}/skills/attach`, {
      skill_id: skillId,
    }),
  
  detachSkill: (workspaceId, agentId, skillId) => 
    apiClient.delete(`/workspaces/${workspaceId}/agents/${agentId}/skills/${skillId}`),
  
  // Conversations
  listConversations: (workspaceId, agentId) => 
    apiClient.get(`/workspaces/${workspaceId}/agents/${agentId}/conversations`),
  
  getConversation: (workspaceId, agentId, conversationId) => 
    apiClient.get(`/workspaces/${workspaceId}/agents/${agentId}/conversations/${conversationId}`),
  
  createConversation: (workspaceId, agentId, data) => 
    apiClient.post(`/workspaces/${workspaceId}/agents/${agentId}/conversations`, data),
  
  sendMessage: (workspaceId, agentId, conversationId, message) => 
    apiClient.post(
      `/workspaces/${workspaceId}/agents/${agentId}/conversations/${conversationId}/messages`,
      { message }
    ),
};
```

### React Query Hooks
```javascript
// src/hooks/useAgents.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { agentsApi } from '../api/agents';
import { useWorkspaceStore } from '../stores/workspaceStore';

export function useAgents() {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['agents', workspaceId],
    queryFn: () => agentsApi.list(workspaceId),
    enabled: !!workspaceId,
  });
}

export function useAgent(agentId) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['agents', workspaceId, agentId],
    queryFn: () => agentsApi.get(workspaceId, agentId),
    enabled: !!workspaceId && !!agentId,
  });
}

export function useCreateAgent() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: (data) => agentsApi.create(workspaceId, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['agents', workspaceId]);
    },
  });
}

export function useConversation(agentId, conversationId) {
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useQuery({
    queryKey: ['conversation', workspaceId, agentId, conversationId],
    queryFn: () => agentsApi.getConversation(workspaceId, agentId, conversationId),
    enabled: !!workspaceId && !!agentId && !!conversationId,
    refetchInterval: 5000, // Poll for new messages
  });
}

export function useSendMessage() {
  const queryClient = useQueryClient();
  const workspaceId = useWorkspaceStore((state) => state.currentWorkspace?.id);

  return useMutation({
    mutationFn: ({ agentId, conversationId, message }) => 
      agentsApi.sendMessage(workspaceId, agentId, conversationId, message),
    onSuccess: (_, { agentId, conversationId }) => {
      queryClient.invalidateQueries(['conversation', workspaceId, agentId, conversationId]);
    },
  });
}
```

---

## 🎨 UI Components

### Agent Chat Interface
```javascript
// src/components/AgentChat.jsx
import { useState, useEffect, useRef } from 'react';
import { useConversation, useSendMessage } from '../hooks/useAgents';
import { Send, Bot, User } from 'lucide-react';

export default function AgentChat({ agentId, conversationId }) {
  const [message, setMessage] = useState('');
  const { data: conversation } = useConversation(agentId, conversationId);
  const sendMessage = useSendMessage();
  const messagesEndRef = useRef(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(scrollToBottom, [conversation]);

  const handleSend = async (e) => {
    e.preventDefault();
    if (!message.trim()) return;

    await sendMessage.mutateAsync({
      agentId,
      conversationId,
      message: message.trim(),
    });
    setMessage('');
  };

  return (
    <div className="flex flex-col h-full bg-white rounded-lg shadow">
      {/* Messages */}
      <div className="flex-1 overflow-y-auto p-4 space-y-4">
        {conversation?.data?.messages?.map((msg, idx) => (
          <div
            key={idx}
            className={`flex gap-3 ${
              msg.role === 'user' ? 'flex-row-reverse' : ''
            }`}
          >
            <div className="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
              {msg.role === 'user' ? (
                <User className="w-5 h-5 text-gray-600" />
              ) : (
                <Bot className="w-5 h-5 text-blue-600" />
              )}
            </div>
            <div
              className={`max-w-[70%] rounded-lg p-3 ${
                msg.role === 'user'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100'
              }`}
            >
              <p className="text-sm whitespace-pre-wrap">{msg.content}</p>
              {msg.skill_used && (
                <p className="text-xs mt-2 opacity-70">
                  Used: {msg.skill_used}
                </p>
              )}
            </div>
          </div>
        ))}
        <div ref={messagesEndRef} />
      </div>

      {/* Input */}
      <form onSubmit={handleSend} className="border-t p-4 flex gap-2">
        <input
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          placeholder="Type your message..."
          className="flex-1 border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <button
          type="submit"
          disabled={sendMessage.isPending || !message.trim()}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2"
        >
          <Send className="w-4 h-4" />
        </button>
      </form>
    </div>
  );
}
```

---

## 💡 Common Use Cases

### 1. Customer Support Chatbot
```javascript
const agent = {
  name: "Support Bot",
  model: "gpt-4o",
  system_prompt: "You are a helpful customer support agent...",
  skills: ["Order Lookup", "Knowledge Base Search", "Escalate to Human"],
};
```

### 2. Sales Qualification Agent
```javascript
const agent = {
  name: "Sales Qualifier",
  model: "gpt-4o",
  system_prompt: "You help qualify sales leads...",
  skills: ["CRM Lookup", "Schedule Demo", "Send Pricing"],
};
```

---

## 🎯 Next Steps

- Read [Module 15: Advanced Workflow Features](./15-advanced-workflow.md)
- Implement agent analytics dashboard
- Add voice interface for agents
