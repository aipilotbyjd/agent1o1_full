export const WebhookEndpoints = {
	list: (ws: string) => `/workspaces/${ws}/webhooks`,
	detail: (ws: string, webhookId: string) => `/workspaces/${ws}/webhooks/${webhookId}`,
	update: (ws: string, webhookId: string) => `/workspaces/${ws}/webhooks/${webhookId}`,
	delete: (ws: string, webhookId: string) => `/workspaces/${ws}/webhooks/${webhookId}`,
} as const;
