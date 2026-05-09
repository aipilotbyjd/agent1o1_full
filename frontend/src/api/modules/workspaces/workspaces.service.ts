import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse } from '@/api/core';
import type {
	TWorkspace,
	TWorkspaceDetail,
	TCreateWorkspaceDto,
	TUpdateWorkspaceDto,
} from '@/types/workspace.type';
import { WorkspaceEndpoints as E } from './workspaces.endpoints';

export const WorkspaceService = {
	list: (signal?: AbortSignal) =>
		axiosClient.get<TApiResponse<TWorkspace[]>>(E.list, { signal }).then(unwrap<TWorkspace[]>),

	detail: (id: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TWorkspaceDetail>>(E.detail(id), { signal })
			.then(unwrap<TWorkspaceDetail>),

	create: (payload: TCreateWorkspaceDto) =>
		axiosClient.post<TApiResponse<TWorkspace>>(E.create, payload).then(unwrap<TWorkspace>),

	update: (id: string, payload: TUpdateWorkspaceDto) =>
		axiosClient
			.put<TApiResponse<TWorkspaceDetail>>(E.update(id), payload)
			.then(unwrap<TWorkspaceDetail>),

	remove: (id: string) => axiosClient.delete(E.delete(id)).then(() => undefined),
};
