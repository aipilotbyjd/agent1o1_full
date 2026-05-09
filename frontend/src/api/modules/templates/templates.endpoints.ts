export const TemplateEndpoints = {
	list: () => '/templates',
	detail: (id: string) => `/templates/${id}`,
	use: (ws: string, id: string) => `/workspaces/${ws}/templates/${id}/use`,
} as const;
