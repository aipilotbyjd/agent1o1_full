export const VariableEndpoints = {
	list: (ws: string) => `/workspaces/${ws}/variables`,
	create: (ws: string) => `/workspaces/${ws}/variables`,
	detail: (ws: string, id: string) => `/workspaces/${ws}/variables/${id}`,
	update: (ws: string, id: string) => `/workspaces/${ws}/variables/${id}`,
	delete: (ws: string, id: string) => `/workspaces/${ws}/variables/${id}`,
} as const;
