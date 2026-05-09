export const NodeTypeEndpoints = {
	list: '/nodes',
	categories: '/node-categories',
	detail: (nodeType: string) => `/nodes/${nodeType}`,
} as const;
