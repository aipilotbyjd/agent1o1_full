import type { TCanvasEdge, TCanvasNode } from '../_types/canvas.type';
import type { TNodeDefinition } from '../_types/node.type';

const makeDemoNode = (
	id: string,
	def: TNodeDefinition,
	position: { x: number; y: number },
	values: Record<string, unknown>,
	status: TCanvasNode['data']['status'] = 'idle',
	durationMs?: number,
): TCanvasNode => ({
	id,
	type:
		def.category === 'input'
			? 'input'
			: def.category === 'output'
				? 'output'
				: def.category === 'note'
					? 'note'
					: 'base',
	position,
	data: {
		defKey: def.key,
		label: def.label,
		values,
		status,
		durationMs,
	},
});

export const makeDemoWorkflow = (catalog: Record<string, TNodeDefinition>) => {
	const trigger = makeDemoNode(
		'node_webhook_trigger',
		catalog['trigger.webhook'],
		{ x: 40, y: 120 },
		{ path: '/lead-intake', method: 'POST' },
		'success',
		42,
	);
	const enrich = makeDemoNode(
		'node_ai_enrich',
		catalog['ai.agent'],
		{ x: 330, y: 80 },
		{
			goal: 'Qualify inbound leads, enrich company context, and produce a concise routing summary.',
			model: 'gpt-5-mini',
		},
		'running',
	);
	const condition = makeDemoNode(
		'node_condition_score',
		catalog['logic.condition'],
		{ x: 650, y: 124 },
		{ expression: 'lead.score >= 80 && lead.region === "enterprise"' },
		'queued',
	);
	const database = makeDemoNode(
		'node_database_sync',
		catalog['data.database'],
		{ x: 960, y: 40 },
		{ table: 'qualified_leads', operation: 'upsert' },
		'idle',
	);
	const api = makeDemoNode(
		'node_api_notify',
		catalog['data.http'],
		{ x: 960, y: 210 },
		{ method: 'POST', url: 'https://api.sales.example/notify' },
		'idle',
	);
	const output = makeDemoNode(
		'node_output_summary',
		catalog['output.display'],
		{ x: 1280, y: 124 },
		{ name: 'routing_summary', type: 'json' },
		'idle',
	);

	const edges: TCanvasEdge[] = [
		{
			id: 'edge_trigger_agent',
			source: trigger.id,
			target: enrich.id,
			sourceHandle: 'payload',
			targetHandle: 'context',
		},
		{
			id: 'edge_agent_condition',
			source: enrich.id,
			target: condition.id,
			sourceHandle: 'decision',
			targetHandle: 'in',
		},
		{
			id: 'edge_condition_database',
			source: condition.id,
			target: database.id,
			sourceHandle: 'true',
			targetHandle: 'record',
		},
		{
			id: 'edge_condition_api',
			source: condition.id,
			target: api.id,
			sourceHandle: 'false',
			targetHandle: 'body',
		},
		{
			id: 'edge_database_output',
			source: database.id,
			target: output.id,
			sourceHandle: 'result',
			targetHandle: 'value',
		},
		{
			id: 'edge_api_output',
			source: api.id,
			target: output.id,
			sourceHandle: 'response',
			targetHandle: 'value',
		},
	];

	return {
		nodes: [trigger, enrich, condition, database, api, output],
		edges,
	};
};
