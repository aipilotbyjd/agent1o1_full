export const billingKeys = {
	all: (ws: string) => ['billing', ws] as const,
	portal: (ws: string) => ['billing', ws, 'portal'] as const,
};
