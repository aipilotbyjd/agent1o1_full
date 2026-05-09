# 🎨 Module 3: Workflow Editor (Visual Canvas)

**The heart of your application** - Visual drag-and-drop workflow builder

**Libraries:** React Flow (recommended), React DnD  
**APIs:** `/api/v1/workspaces/{workspace}/workflows/*` and `/api/v1/workspaces/{workspace}/catalog/nodes`

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [APIs](#apis)
4. [State Management](#state-management)
5. [Components](#components)
6. [Implementation Guide](#implementation-guide)
7. [Code Examples](#code-examples)

---

## 🎯 Overview

### Features to Implement
- ✅ Visual drag-and-drop canvas
- ✅ Node palette with search
- ✅ Node configuration panel
- ✅ Connection drawing
- ✅ Zoom and pan
- ✅ Minimap
- ✅ Undo/Redo
- ✅ Auto-save
- ✅ Real-time execution visualization
- ✅ Node validation
- ✅ Expression builder

### User Flow
```
1. Open workflow editor
2. Load workflow data (if editing)
3. Fetch available nodes from catalog
4. User drags nodes onto canvas
5. User connects nodes
6. User configures each node
7. Auto-save changes
8. User executes workflow
9. Display execution results
```

---

## 🔗 APIs

### 1. Get Workflow
```http
GET /api/v1/workspaces/{workspace_id}/workflows/{workflow_id}

Response:
{
  "data": {
    "id": "uuid",
    "name": "My Workflow",
    "description": "...",
    "nodes": [
      {
        "id": "node-1",
        "type": "core.manual_trigger",
        "position": { "x": 100, "y": 100 },
        "config": {}
      },
      {
        "id": "node-2",
        "type": "data.json",
        "position": { "x": 300, "y": 100 },
        "config": {
          "operation": "parse",
          "json_string": "..."
        }
      }
    ],
    "edges": [
      {
        "id": "edge-1",
        "source": "node-1",
        "target": "node-2",
        "sourceHandle": null,
        "targetHandle": null
      }
    ],
    "settings": {
      "timezone": "UTC",
      "error_workflow": null
    }
  }
}
```

### 2. Update Workflow
```http
PUT /api/v1/workspaces/{workspace_id}/workflows/{workflow_id}
Content-Type: application/json

{
  "name": "Updated Name",
  "nodes": [...],
  "edges": [...]
}

Response: Same as GET
```

### 3. Get Node Catalog
```http
GET /api/v1/workspaces/{workspace_id}/catalog/nodes

Response:
{
  "data": [
    {
      "type": "data.json",
      "name": "JSON",
      "description": "Parse, stringify, extract JSON",
      "category": "data",
      "icon": "code-bracket",
      "color": "#10B981",
      "config_schema": {
        "type": "object",
        "properties": {
          "operation": {
            "type": "string",
            "enum": ["parse", "stringify", "extract", "merge", "validate"],
            "default": "parse"
          }
        }
      },
      "input_schema": {...},
      "output_schema": {...}
    },
    // ... 40+ more nodes
  ]
}
```

### 4. Execute Workflow
```http
POST /api/v1/workspaces/{workspace_id}/workflows/{workflow_id}/execute
Content-Type: application/json

{
  "input": {
    "test_data": "value"
  }
}

Response:
{
  "data": {
    "execution_id": "uuid",
    "status": "running",
    ...
  }
}
```

### 5. Get Execution Status (for real-time updates)
```http
GET /api/v1/workspaces/{workspace_id}/executions/{execution_id}

Response:
{
  "data": {
    "id": "uuid",
    "status": "completed",
    "nodes": {
      "node-1": {
        "status": "completed",
        "output": {...},
        "duration_ms": 45
      },
      "node-2": {
        "status": "completed",
        "output": {...},
        "duration_ms": 120
      }
    }
  }
}
```

---

## 🗄️ State Management

### Workflow Editor Store
```javascript
// src/stores/workflowEditorStore.js
import { create } from 'zustand';

export const useWorkflowEditorStore = create((set, get) => ({
  // Canvas state
  nodes: [],
  edges: [],
  selectedNode: null,
  viewport: { x: 0, y: 0, zoom: 1 },
  
  // Workflow metadata
  workflowId: null,
  workflowName: '',
  isDirty: false,
  isSaving: false,
  
  // Node catalog
  availableNodes: [],
  nodeCategories: [],
  
  // Execution state
  isExecuting: false,
  currentExecution: null,
  executionResults: {},
  
  // Actions
  setNodes: (nodes) => set({ nodes, isDirty: true }),
  setEdges: (edges) => set({ edges, isDirty: true }),
  
  addNode: (node) => set((state) => ({
    nodes: [...state.nodes, node],
    isDirty: true
  })),
  
  updateNode: (nodeId, updates) => set((state) => ({
    nodes: state.nodes.map(n => 
      n.id === nodeId ? { ...n, ...updates } : n
    ),
    isDirty: true
  })),
  
  deleteNode: (nodeId) => set((state) => ({
    nodes: state.nodes.filter(n => n.id !== nodeId),
    edges: state.edges.filter(e => 
      e.source !== nodeId && e.target !== nodeId
    ),
    isDirty: true
  })),
  
  selectNode: (nodeId) => set({ selectedNode: nodeId }),
  
  setAvailableNodes: (nodes) => {
    const categories = [...new Set(nodes.map(n => n.category))];
    set({ availableNodes: nodes, nodeCategories: categories });
  },
  
  markSaved: () => set({ isDirty: false }),
  
  setExecutionResult: (nodeId, result) => set((state) => ({
    executionResults: {
      ...state.executionResults,
      [nodeId]: result
    }
  })),
  
  clearExecutionResults: () => set({ executionResults: {} }),
}));
```

---

## 🎨 Components Structure

```
src/components/workflow-editor/
├── WorkflowEditor.jsx         # Main container
├── WorkflowCanvas.jsx          # React Flow canvas
├── NodePalette.jsx             # Left sidebar with nodes
├── NodeConfigPanel.jsx         # Right sidebar for configuration
├── WorkflowToolbar.jsx         # Top toolbar (save, execute, etc.)
├── MiniMap.jsx                 # Canvas minimap
├── nodes/
│   ├── CustomNode.jsx          # Base node component
│   ├── TriggerNode.jsx         # Trigger node UI
│   ├── ActionNode.jsx          # Action node UI
│   └── ...
├── NodeSearch.jsx              # Search in palette
└── ExecutionOverlay.jsx        # Shows execution progress
```

---

## 💻 Implementation

### 1. Install Dependencies
```bash
npm install reactflow
npm install @reactflow/minimap @reactflow/controls
npm install zustand
npm install react-query
```

### 2. Main Workflow Editor Component
```jsx
// src/components/workflow-editor/WorkflowEditor.jsx
import { useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import ReactFlow, {
  Background,
  Controls,
  MiniMap,
  useNodesState,
  useEdgesState,
  addEdge,
} from 'reactflow';
import 'reactflow/dist/style.css';

import { workflowsApi } from '../../api/workflows';
import { catalogApi } from '../../api/catalog';
import { useWorkflowEditorStore } from '../../stores/workflowEditorStore';
import NodePalette from './NodePalette';
import NodeConfigPanel from './NodeConfigPanel';
import WorkflowToolbar from './WorkflowToolbar';
import CustomNode from './nodes/CustomNode';

// Define custom node types
const nodeTypes = {
  custom: CustomNode,
};

export default function WorkflowEditor() {
  const { workspaceId, workflowId } = useParams();
  const queryClient = useQueryClient();
  
  // Local React Flow state
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  
  // Global store
  const { setAvailableNodes, selectedNode, selectNode } = useWorkflowEditorStore();
  
  // Fetch workflow data
  const { data: workflow, isLoading } = useQuery({
    queryKey: ['workflow', workflowId],
    queryFn: () => workflowsApi.get(workspaceId, workflowId),
    enabled: !!workflowId,
  });
  
  // Fetch available nodes
  const { data: catalog } = useQuery({
    queryKey: ['catalog', 'nodes', workspaceId],
    queryFn: () => catalogApi.getNodes(workspaceId),
  });
  
  // Update mutation
  const updateMutation = useMutation({
    mutationFn: (data) => workflowsApi.update(workspaceId, workflowId, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['workflow', workflowId]);
    },
  });
  
  // Load workflow data
  useEffect(() => {
    if (workflow?.data) {
      setNodes(workflow.data.nodes || []);
      setEdges(workflow.data.edges || []);
    }
  }, [workflow, setNodes, setEdges]);
  
  // Load catalog
  useEffect(() => {
    if (catalog?.data) {
      setAvailableNodes(catalog.data);
    }
  }, [catalog, setAvailableNodes]);
  
  // Handle connection
  const onConnect = useCallback((connection) => {
    setEdges((eds) => addEdge(connection, eds));
  }, [setEdges]);
  
  // Handle node click
  const onNodeClick = useCallback((event, node) => {
    selectNode(node.id);
  }, [selectNode]);
  
  // Auto-save (debounced)
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (nodes.length > 0) {
        updateMutation.mutate({
          nodes,
          edges,
        });
      }
    }, 2000); // Save 2 seconds after last change
    
    return () => clearTimeout(timeoutId);
  }, [nodes, edges]);
  
  if (isLoading) {
    return <div>Loading workflow...</div>;
  }
  
  return (
    <div className="flex h-screen">
      {/* Left: Node Palette */}
      <NodePalette />
      
      {/* Center: Canvas */}
      <div className="flex-1 relative">
        <WorkflowToolbar 
          workflowId={workflowId}
          workspaceId={workspaceId}
          nodes={nodes}
          edges={edges}
        />
        
        <ReactFlow
          nodes={nodes}
          edges={edges}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          onNodeClick={onNodeClick}
          nodeTypes={nodeTypes}
          fitView
        >
          <Background />
          <Controls />
          <MiniMap />
        </ReactFlow>
        
        {updateMutation.isPending && (
          <div className="absolute top-4 right-4 bg-blue-500 text-white px-3 py-1 rounded">
            Saving...
          </div>
        )}
      </div>
      
      {/* Right: Configuration Panel */}
      {selectedNode && (
        <NodeConfigPanel 
          nodeId={selectedNode}
          nodes={nodes}
          setNodes={setNodes}
        />
      )}
    </div>
  );
}
```

### 3. Node Palette Component
```jsx
// src/components/workflow-editor/NodePalette.jsx
import { useState, useMemo } from 'react';
import { useWorkflowEditorStore } from '../../stores/workflowEditorStore';

export default function NodePalette() {
  const { availableNodes, nodeCategories } = useWorkflowEditorStore();
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('all');
  
  const filteredNodes = useMemo(() => {
    return availableNodes.filter(node => {
      const matchesSearch = node.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          node.description?.toLowerCase().includes(searchTerm.toLowerCase());
      const matchesCategory = selectedCategory === 'all' || node.category === selectedCategory;
      return matchesSearch && matchesCategory;
    });
  }, [availableNodes, searchTerm, selectedCategory]);
  
  const onDragStart = (event, nodeType) => {
    event.dataTransfer.setData('application/reactflow', nodeType);
    event.dataTransfer.effectAllowed = 'move';
  };
  
  return (
    <div className="w-64 bg-gray-50 border-r overflow-y-auto">
      <div className="p-4">
        <h2 className="text-lg font-semibold mb-4">Nodes</h2>
        
        {/* Search */}
        <input
          type="text"
          placeholder="Search nodes..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="w-full px-3 py-2 border rounded mb-4"
        />
        
        {/* Category filter */}
        <select
          value={selectedCategory}
          onChange={(e) => setSelectedCategory(e.target.value)}
          className="w-full px-3 py-2 border rounded mb-4"
        >
          <option value="all">All Categories</option>
          {nodeCategories.map(cat => (
            <option key={cat} value={cat}>
              {cat.charAt(0).toUpperCase() + cat.slice(1)}
            </option>
          ))}
        </select>
        
        {/* Node list */}
        <div className="space-y-2">
          {filteredNodes.map(node => (
            <div
              key={node.type}
              draggable
              onDragStart={(e) => onDragStart(e, node.type)}
              className="p-3 bg-white border rounded cursor-move hover:shadow-md transition-shadow"
            >
              <div className="flex items-center gap-2">
                <div 
                  className="w-3 h-3 rounded-full"
                  style={{ backgroundColor: node.color }}
                />
                <div>
                  <div className="font-medium">{node.name}</div>
                  <div className="text-xs text-gray-500">{node.description}</div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
```

### 4. Custom Node Component
```jsx
// src/components/workflow-editor/nodes/CustomNode.jsx
import { memo } from 'react';
import { Handle, Position } from 'reactflow';

const CustomNode = ({ data, selected }) => {
  return (
    <div
      className={`px-4 py-3 rounded-lg border-2 bg-white shadow-sm min-w-[200px] ${
        selected ? 'border-blue-500 shadow-md' : 'border-gray-300'
      }`}
    >
      <Handle type="target" position={Position.Left} />
      
      <div className="flex items-center gap-2">
        <div 
          className="w-3 h-3 rounded-full"
          style={{ backgroundColor: data.color || '#666' }}
        />
        <div>
          <div className="font-medium text-sm">{data.label}</div>
          <div className="text-xs text-gray-500">{data.type}</div>
        </div>
      </div>
      
      {data.config?.operation && (
        <div className="mt-2 text-xs text-gray-600">
          {data.config.operation}
        </div>
      )}
      
      <Handle type="source" position={Position.Right} />
    </div>
  );
};

export default memo(CustomNode);
```

### 5. Node Configuration Panel
```jsx
// src/components/workflow-editor/NodeConfigPanel.jsx
import { useState, useEffect } from 'react';
import { useWorkflowEditorStore } from '../../stores/workflowEditorStore';

export default function NodeConfigPanel({ nodeId, nodes, setNodes }) {
  const { availableNodes } = useWorkflowEditorStore();
  
  const node = nodes.find(n => n.id === nodeId);
  const nodeDefinition = availableNodes.find(n => n.type === node?.type);
  
  const [config, setConfig] = useState(node?.config || {});
  
  useEffect(() => {
    setConfig(node?.config || {});
  }, [node]);
  
  const handleConfigChange = (key, value) => {
    const newConfig = { ...config, [key]: value };
    setConfig(newConfig);
    
    // Update node in React Flow
    setNodes((nds) =>
      nds.map((n) =>
        n.id === nodeId ? { ...n, config: newConfig } : n
      )
    );
  };
  
  if (!node || !nodeDefinition) {
    return null;
  }
  
  const configSchema = nodeDefinition.config_schema?.properties || {};
  
  return (
    <div className="w-80 bg-white border-l p-4 overflow-y-auto">
      <h3 className="text-lg font-semibold mb-4">{nodeDefinition.name}</h3>
      <p className="text-sm text-gray-600 mb-4">{nodeDefinition.description}</p>
      
      <div className="space-y-4">
        {Object.entries(configSchema).map(([key, schema]) => (
          <div key={key}>
            <label className="block text-sm font-medium mb-1">
              {schema.description || key}
            </label>
            
            {schema.enum ? (
              // Dropdown for enum
              <select
                value={config[key] || schema.default || ''}
                onChange={(e) => handleConfigChange(key, e.target.value)}
                className="w-full px-3 py-2 border rounded"
              >
                {schema.enum.map(option => (
                  <option key={option} value={option}>{option}</option>
                ))}
              </select>
            ) : schema.type === 'boolean' ? (
              // Checkbox for boolean
              <input
                type="checkbox"
                checked={config[key] || schema.default || false}
                onChange={(e) => handleConfigChange(key, e.target.checked)}
                className="w-4 h-4"
              />
            ) : schema.type === 'integer' || schema.type === 'number' ? (
              // Number input
              <input
                type="number"
                value={config[key] || schema.default || ''}
                onChange={(e) => handleConfigChange(key, Number(e.target.value))}
                min={schema.minimum}
                max={schema.maximum}
                className="w-full px-3 py-2 border rounded"
              />
            ) : (
              // Text input
              <input
                type="text"
                value={config[key] || schema.default || ''}
                onChange={(e) => handleConfigChange(key, e.target.value)}
                placeholder={schema.description}
                className="w-full px-3 py-2 border rounded"
              />
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
```

### 6. Workflow Toolbar
```jsx
// src/components/workflow-editor/WorkflowToolbar.jsx
import { useMutation } from '@tanstack/react-query';
import { workflowsApi } from '../../api/workflows';

export default function WorkflowToolbar({ workflowId, workspaceId, nodes, edges }) {
  const executeMutation = useMutation({
    mutationFn: () => workflowsApi.execute(workspaceId, workflowId, {}),
  });
  
  const handleExecute = () => {
    executeMutation.mutate();
  };
  
  return (
    <div className="absolute top-0 left-0 right-0 bg-white border-b p-2 flex items-center gap-4 z-10">
      <button
        onClick={handleExecute}
        disabled={executeMutation.isPending || nodes.length === 0}
        className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
      >
        {executeMutation.isPending ? 'Executing...' : 'Execute'}
      </button>
      
      {executeMutation.isSuccess && (
        <span className="text-green-600">Execution started!</span>
      )}
      
      {executeMutation.isError && (
        <span className="text-red-600">Execution failed</span>
      )}
      
      <div className="ml-auto text-sm text-gray-600">
        {nodes.length} nodes, {edges.length} connections
      </div>
    </div>
  );
}
```

---

## 📁 API Module

```javascript
// src/api/workflows.js
import apiClient from './client';

export const workflowsApi = {
  list: (workspaceId) =>
    apiClient.get(`/workspaces/${workspaceId}/workflows`).then(res => res.data),
  
  get: (workspaceId, workflowId) =>
    apiClient.get(`/workspaces/${workspaceId}/workflows/${workflowId}`).then(res => res.data),
  
  create: (workspaceId, data) =>
    apiClient.post(`/workspaces/${workspaceId}/workflows`, data).then(res => res.data),
  
  update: (workspaceId, workflowId, data) =>
    apiClient.put(`/workspaces/${workspaceId}/workflows/${workflowId}`, data).then(res => res.data),
  
  delete: (workspaceId, workflowId) =>
    apiClient.delete(`/workspaces/${workspaceId}/workflows/${workflowId}`).then(res => res.data),
  
  execute: (workspaceId, workflowId, input) =>
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/execute`, { input }).then(res => res.data),
  
  activate: (workspaceId, workflowId) =>
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/activate`).then(res => res.data),
  
  deactivate: (workspaceId, workflowId) =>
    apiClient.post(`/workspaces/${workspaceId}/workflows/${workflowId}/deactivate`).then(res => res.data),
};

// src/api/catalog.js
import apiClient from './client';

export const catalogApi = {
  getNodes: (workspaceId) =>
    apiClient.get(`/workspaces/${workspaceId}/catalog/nodes`).then(res => res.data),
  
  getNode: (workspaceId, nodeType) =>
    apiClient.get(`/workspaces/${workspaceId}/catalog/nodes/${nodeType}`).then(res => res.data),
  
  getCredentialTypes: (workspaceId) =>
    apiClient.get(`/workspaces/${workspaceId}/catalog/credential-types`).then(res => res.data),
};
```

---

## ✅ Checklist

- [ ] Install React Flow
- [ ] Setup workflow editor store
- [ ] Create main WorkflowEditor component
- [ ] Implement NodePalette with drag & drop
- [ ] Create CustomNode component
- [ ] Implement NodeConfigPanel
- [ ] Add WorkflowToolbar
- [ ] Fetch and display node catalog
- [ ] Handle node connections
- [ ] Implement auto-save
- [ ] Add execution functionality
- [ ] Show execution progress
- [ ] Add zoom and pan controls
- [ ] Add minimap
- [ ] Implement node search
- [ ] Add keyboard shortcuts

---

**Next Module:** [Workflow Management →](./04-workflow-management.md)
