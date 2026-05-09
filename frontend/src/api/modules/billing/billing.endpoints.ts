export const BillingEndpoints = {
	checkout: (ws: string) => `/workspaces/${ws}/billing/checkout`,
	buyCredits: (ws: string) => `/workspaces/${ws}/billing/credits`,
	portal: (ws: string) => `/workspaces/${ws}/billing/portal`,
} as const;
