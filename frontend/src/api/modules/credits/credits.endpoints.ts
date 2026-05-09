export const CreditEndpoints = {
	balance: (ws: string) => `/workspaces/${ws}/credits/balance`,
	transactions: (ws: string) => `/workspaces/${ws}/credits/transactions`,
} as const;
