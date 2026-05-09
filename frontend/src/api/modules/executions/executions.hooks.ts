import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { notify } from '@/api/core';
import type { TListParams } from '@/api/core';
import { ExecutionService } from './executions.service';
import { executionKeys } from './executions.keys';

export const useExecutions = (ws: string, params?: TListParams) =>
	useQuery({
		queryKey: executionKeys.list(ws, params),
		queryFn: ({ signal }) => ExecutionService.list(ws, params, signal),
		enabled: !!ws,
	});

export const useExecution = (ws: string, id: string) =>
	useQuery({
		queryKey: executionKeys.detail(ws, id),
		queryFn: ({ signal }) => ExecutionService.detail(ws, id, signal),
		enabled: !!ws && !!id,
	});

export const useExecutionLogs = (ws: string, id: string) =>
	useQuery({
		queryKey: executionKeys.logs(id),
		queryFn: ({ signal }) => ExecutionService.logs(ws, id, signal),
		enabled: !!ws && !!id,
	});

export const useCancelExecution = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: (id: string) => ExecutionService.cancel(ws, id),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: executionKeys.all(ws) });
			notify.success('Execution cancelled');
		},
		onError: notify.fromError('Failed to cancel execution'),
	});
};

export const useRetryExecution = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: (id: string) => ExecutionService.retry(ws, id),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: executionKeys.all(ws) });
			notify.success('Execution retry started');
		},
		onError: notify.fromError('Failed to retry execution'),
	});
};
