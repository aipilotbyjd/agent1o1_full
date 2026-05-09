import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse, TMessageResponse } from '@/api/core';
import type {
	TWorkspaceMember,
	TWorkspaceInvitation,
	TUpdateMemberRoleDto,
	TSendInvitationDto,
	TWorkspaceSettings,
} from '@/types/workspace.type';
import {
	WorkspaceMemberEndpoints as M,
	WorkspaceInvitationEndpoints as I,
	WorkspaceSettingsEndpoints as S,
} from './workspace-members.endpoints';

export const WorkspaceMemberService = {
	list: (ws: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TWorkspaceMember[]>>(M.list(ws), { signal })
			.then(unwrap<TWorkspaceMember[]>),

	updateRole: (ws: string, userId: string, payload: TUpdateMemberRoleDto) =>
		axiosClient
			.put<TApiResponse<TWorkspaceMember>>(M.updateRole(ws, userId), payload)
			.then(unwrap<TWorkspaceMember>),

	remove: (ws: string, userId: string) =>
		axiosClient.delete(M.remove(ws, userId)).then(() => undefined),

	transferOwnership: (ws: string, newOwnerId: string) =>
		axiosClient
			.post<TMessageResponse>(M.transferOwnership(ws), { new_owner_id: newOwnerId })
			.then((r) => r.data),

	leave: (ws: string) => axiosClient.post<TMessageResponse>(M.leave(ws)).then((r) => r.data),
};

export const WorkspaceInvitationService = {
	list: (ws: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TWorkspaceInvitation[]>>(I.list(ws), { signal })
			.then(unwrap<TWorkspaceInvitation[]>),

	send: (ws: string, payload: TSendInvitationDto) =>
		axiosClient
			.post<TApiResponse<TWorkspaceInvitation>>(I.send(ws), payload)
			.then(unwrap<TWorkspaceInvitation>),

	cancel: (ws: string, invitationId: string) =>
		axiosClient.delete(I.cancel(ws, invitationId)).then(() => undefined),
};

export const WorkspaceSettingsService = {
	fetch: (ws: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TWorkspaceSettings>>(S.get(ws), { signal })
			.then(unwrap<TWorkspaceSettings>),

	update: (ws: string, payload: Partial<TWorkspaceSettings>) =>
		axiosClient.put<TMessageResponse>(S.update(ws), payload).then((r) => r.data),
};
