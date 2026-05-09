/**
 * Execution Types
 * Matches Laravel backend from docs/frontend/modules/05-execution-dashboard.md
 */

export type TExecutionStatus =
	| 'queued'
	| 'running'
	| 'completed'
	| 'failed'
	| 'cancelled'
	| 'timeout';
export type TExecutionTrigger = 'manual' | 'webhook' | 'schedule' | 'test';

// Execution list item — as returned in GET /workspaces/{id}/executions
export type TExecution = {
	id: string;
	workflow_id: string;
	workspace_id?: string;
	workflow_name?: string;
	status: TExecutionStatus;
	mode?: TExecutionTrigger;
	started_at: string | null;
	finished_at?: string | null;
	completed_at?: string | null;
	duration_ms: number | null;
	trigger?: TExecutionTrigger;
	error?: unknown;
	attempt?: number;
	max_attempts?: number;
};

// Execution detail — as returned by GET /workspaces/{id}/executions/{id}
export type TExecutionDetail = {
	id: string;
	status: TExecutionStatus;
	nodes: Record<string, TExecutionNode>;
};

export type TExecutionNode = {
	status: TExecutionStatus;
	output: unknown;
	duration_ms: number;
};

// Execution log entry — as returned by GET /workspaces/{id}/executions/{id}/logs
export type TExecutionLog = {
	timestamp: string;
	level: 'info' | 'warning' | 'error' | 'debug';
	message: string;
	node_id: string;
};
