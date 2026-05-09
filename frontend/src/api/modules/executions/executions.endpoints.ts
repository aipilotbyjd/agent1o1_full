export const ExecutionEndpoints = {
	list: (ws: string) => `/workspaces/${ws}/executions`,
	detail: (ws: string, id: string) => `/workspaces/${ws}/executions/${id}`,
	logs: (ws: string, id: string) => `/workspaces/${ws}/executions/${id}/logs`,
	cancel: (ws: string, id: string) => `/workspaces/${ws}/executions/${id}/cancel`,
	retry: (ws: string, id: string) => `/workspaces/${ws}/executions/${id}/retry`,
	nodes: (ws: string, id: string) => `/workspaces/${ws}/executions/${id}/nodes`,
	nodeDetail: (ws: string, id: string, nodeId: string) =>
		`/workspaces/${ws}/executions/${id}/nodes/${nodeId}`,
	stats: (ws: string) => `/workspaces/${ws}/executions/stats`,
	bulkDelete: (ws: string) => `/workspaces/${ws}/executions/bulk`,
} as const;
