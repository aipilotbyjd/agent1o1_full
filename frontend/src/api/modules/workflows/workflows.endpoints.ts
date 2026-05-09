export const WorkflowEndpoints = {
	list: (ws: string) => `/workspaces/${ws}/workflows`,
	create: (ws: string) => `/workspaces/${ws}/workflows`,
	detail: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}`,
	update: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}`,
	delete: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}`,
	execute: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}/execute`,
	activate: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}/activate`,
	deactivate: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}/deactivate`,
	duplicate: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}/duplicate`,
	import: (ws: string) => `/workspaces/${ws}/workflows/import`,
	export: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}/export`,
	executions: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}/executions`,
} as const;

export const WorkflowEditorEndpoints = {
	versions: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}/versions`,
	version: (ws: string, id: string, version: string) =>
		`/workspaces/${ws}/workflows/${id}/versions/${version}`,
	publish: (ws: string, id: string, version: string) =>
		`/workspaces/${ws}/workflows/${id}/versions/${version}/publish`,
	rollback: (ws: string, id: string, version: string) =>
		`/workspaces/${ws}/workflows/${id}/versions/${version}/rollback`,
	compareVersions: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}/versions/diff`,
	validate: (ws: string) => `/workspaces/${ws}/workflows/validate`,
	testNode: (ws: string) => `/workspaces/${ws}/workflows/test-node`,
	clone: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}/clone`,
	pinnedData: (ws: string, id: string) => `/workspaces/${ws}/workflows/${id}/pinned-data`,
	pinnedDataNode: (ws: string, id: string, nodeId: string) =>
		`/workspaces/${ws}/workflows/${id}/pinned-data/${nodeId}`,
} as const;

export const WorkflowShareEndpoints = {
	list: (ws: string, workflowId: string) => `/workspaces/${ws}/workflows/${workflowId}/shares`,
	create: (ws: string, workflowId: string) => `/workspaces/${ws}/workflows/${workflowId}/shares`,
	update: (ws: string, workflowId: string, shareId: string) =>
		`/workspaces/${ws}/workflows/${workflowId}/shares/${shareId}`,
	delete: (ws: string, workflowId: string, shareId: string) =>
		`/workspaces/${ws}/workflows/${workflowId}/shares/${shareId}`,
	viewPublic: (token: string) => `/shared/${token}`,
	clonePublic: (ws: string, token: string) => `/workspaces/${ws}/shared/${token}/clone`,
} as const;
