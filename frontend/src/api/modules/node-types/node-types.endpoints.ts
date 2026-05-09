export const NodeTypeEndpoints = {
	list: '/nodes',
	categories: '/node-categories',
	categoryDetail: (id: string) => `/node-categories/${id}`,
	detail: (nodeType: string) => `/nodes/${nodeType}`,
} as const;
