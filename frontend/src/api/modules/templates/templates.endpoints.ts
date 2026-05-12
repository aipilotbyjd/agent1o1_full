export const TemplateEndpoints = {
	list: () => '/templates',
	featured: () => '/templates/featured',
	categories: () => '/templates/categories',
	detail: (id: string) => `/templates/${id}`,
	trackView: (id: string) => `/templates/${id}/view`,
	use: (ws: string, id: string) => `/workspaces/${ws}/templates/${id}/use`,
} as const;
