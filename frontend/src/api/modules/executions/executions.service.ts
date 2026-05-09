import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse, TListParams } from '@/api/core';
import type { TExecution, TExecutionDetail, TExecutionLog } from '@/types/execution.type';
import { ExecutionEndpoints as E } from './executions.endpoints';

export const ExecutionService = {
	list: (ws: string, params?: TListParams, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TExecution[]>>(E.list(ws), { params, signal })
			.then(unwrap<TExecution[]>),

	detail: (ws: string, id: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TExecutionDetail>>(E.detail(ws, id), { signal })
			.then(unwrap<TExecutionDetail>),

	logs: (ws: string, id: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TExecutionLog[]>>(E.logs(ws, id), { signal })
			.then(unwrap<TExecutionLog[]>),

	cancel: (ws: string, id: string) => axiosClient.post(E.cancel(ws, id)).then(() => undefined),

	retry: (ws: string, id: string) => axiosClient.post(E.retry(ws, id)).then(() => undefined),
};
