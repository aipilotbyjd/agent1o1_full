import type { TListParams } from './api.type';

export type TCreditBalance = {
	balance: number;
	plan: string;
	monthly_quota: number;
	monthly_used: number;
	period_start: string;
	period_end: string;
};

export type TCreditTransactionType =
	| 'execution'
	| 'purchase'
	| 'refund'
	| 'monthly_grant'
	| 'adjustment';

export type TCreditTransaction = {
	id: string;
	workspace_id: string;
	type: TCreditTransactionType;
	amount: number;
	balance_after: number;
	description: string;
	metadata: Record<string, unknown>;
	created_at: string;
};

export type TCreditTransactionFilters = TListParams & {
	type?: TCreditTransactionType;
	from?: string;
	to?: string;
};
