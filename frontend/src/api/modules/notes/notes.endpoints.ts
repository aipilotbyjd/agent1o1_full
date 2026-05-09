export const NoteEndpoints = {
	list: (ws: string) => `/workspaces/${ws}/notes`,
	detail: (ws: string, noteId: string) => `/workspaces/${ws}/notes/${noteId}`,
	create: (ws: string) => `/workspaces/${ws}/notes`,
	update: (ws: string, noteId: string) => `/workspaces/${ws}/notes/${noteId}`,
	delete: (ws: string, noteId: string) => `/workspaces/${ws}/notes/${noteId}`,
} as const;
