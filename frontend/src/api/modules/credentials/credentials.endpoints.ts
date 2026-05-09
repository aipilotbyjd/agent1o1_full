export const CredentialEndpoints = {
	list: (ws: string) => `/workspaces/${ws}/credentials`,
	create: (ws: string) => `/workspaces/${ws}/credentials`,
	detail: (ws: string, id: string) => `/workspaces/${ws}/credentials/${id}`,
	update: (ws: string, id: string) => `/workspaces/${ws}/credentials/${id}`,
	delete: (ws: string, id: string) => `/workspaces/${ws}/credentials/${id}`,
	test: (ws: string, id: string) => `/workspaces/${ws}/credentials/${id}/test`,
} as const;

export const OAuthEndpoints = {
	initiate: (ws: string) => `/workspaces/${ws}/oauth/initiate`,
	callback: () => `/oauth/callback`,
} as const;
