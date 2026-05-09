export const workspaceKeys = {
	all: () => ['workspaces'] as const,
	list: () => ['workspaces', 'list'] as const,
	detail: (id: string) => ['workspaces', 'detail', id] as const,
};
