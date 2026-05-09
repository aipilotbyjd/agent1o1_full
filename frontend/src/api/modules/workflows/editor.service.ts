import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse, TPaginatedResponse } from '@/api/core';
import type {
	IWorkflow,
	IWorkflowVersion,
	IWorkflowVersionComparison,
	IWorkflowValidationResult,
	ITestNodeDto,
	ITestNodeResult,
	ICloneWorkflowDto,
	IWorkflowNode,
	IWorkflowConnection,
	IWorkflowPinnedData,
	ISetPinnedDataDto,
	TStoreWorkflowVersionDto,
} from '@/types/workflow.type';
import { WorkflowEditorEndpoints as E } from './workflows.endpoints';

/**
 * Workflow editor-specific operations:
 * clone, validate, test-node, versions, pinned-data.
 * Standard CRUD + execute/activate/deactivate/duplicate live in workflows.service.ts.
 */
export const WorkflowEditorService = {
	clone: (ws: string, id: string, body: ICloneWorkflowDto) =>
		axiosClient.post<TApiResponse<IWorkflow>>(E.clone(ws, id), body).then(unwrap<IWorkflow>),

	validate: (ws: string, nodes: IWorkflowNode[], connections: IWorkflowConnection[]) =>
		axiosClient
			.post<TApiResponse<IWorkflowValidationResult>>(E.validate(ws), { nodes, connections })
			.then(unwrap<IWorkflowValidationResult>),

	testNode: (ws: string, body: ITestNodeDto) =>
		axiosClient
			.post<TApiResponse<ITestNodeResult>>(E.testNode(ws), body)
			.then(unwrap<ITestNodeResult>),

	listVersions: (ws: string, workflowId: string, signal?: AbortSignal) =>
		axiosClient
			.get<TPaginatedResponse<IWorkflowVersion>>(E.versions(ws, workflowId), { signal })
			.then((r) => r.data.data),

	createVersion: (ws: string, workflowId: string, body: TStoreWorkflowVersionDto) =>
		axiosClient
			.post<TApiResponse<IWorkflowVersion>>(E.versions(ws, workflowId), body)
			.then(unwrap<IWorkflowVersion>),

	publishVersion: (ws: string, id: string, version: string) =>
		axiosClient
			.post<TApiResponse<IWorkflowVersion>>(E.publish(ws, id, version))
			.then(unwrap<IWorkflowVersion>),

	rollbackVersion: (ws: string, id: string, version: string) =>
		axiosClient
			.post<TApiResponse<IWorkflowVersion>>(E.rollback(ws, id, version))
			.then(unwrap<IWorkflowVersion>),

	compareVersions: (ws: string, id: string, from: number, to: number, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<IWorkflowVersionComparison>>(E.compareVersions(ws, id), {
				params: { from, to },
				signal,
			})
			.then(unwrap<IWorkflowVersionComparison>),

	listPinnedData: (ws: string, workflowId: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<IWorkflowPinnedData[]>>(E.pinnedData(ws, workflowId), { signal })
			.then(unwrap<IWorkflowPinnedData[]>),

	setPinnedData: (ws: string, id: string, body: ISetPinnedDataDto) =>
		axiosClient
			.post<TApiResponse<IWorkflowPinnedData>>(E.pinnedData(ws, id), body)
			.then(unwrap<IWorkflowPinnedData>),

	deletePinnedData: (ws: string, id: string, nodeId: string) =>
		axiosClient.delete(E.pinnedDataNode(ws, id, nodeId)).then(() => undefined),
};
