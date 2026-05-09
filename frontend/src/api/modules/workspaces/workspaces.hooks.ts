import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { notify } from '@/api/core';
import type { TCreateWorkspaceDto, TUpdateWorkspaceDto } from '@/types/workspace.type';
import { WorkspaceService } from './workspaces.service';
import { workspaceKeys } from './workspaces.keys';

export const useWorkspaces = () =>
	useQuery({
		queryKey: workspaceKeys.list(),
		queryFn: ({ signal }) => WorkspaceService.list(signal),
	});

export const useWorkspace = (id: string) =>
	useQuery({
		queryKey: workspaceKeys.detail(id),
		queryFn: ({ signal }) => WorkspaceService.detail(id, signal),
		enabled: !!id,
	});

export const useCreateWorkspace = () => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: (payload: TCreateWorkspaceDto) => WorkspaceService.create(payload),
		onSuccess: (w) => {
			qc.invalidateQueries({ queryKey: workspaceKeys.all() });
			notify.success(`Workspace "${w.name}" created`);
		},
		onError: notify.fromError('Failed to create workspace'),
	});
};

export const useUpdateWorkspace = () => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: ({ id, body }: { id: string; body: TUpdateWorkspaceDto }) =>
			WorkspaceService.update(id, body),
		onSuccess: (w) => {
			qc.invalidateQueries({ queryKey: workspaceKeys.all() });
			notify.success(`Workspace "${w.name}" updated`);
		},
		onError: notify.fromError('Failed to update workspace'),
	});
};

export const useDeleteWorkspace = () => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: (id: string) => WorkspaceService.remove(id),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: workspaceKeys.all() });
			notify.success('Workspace deleted');
		},
		onError: notify.fromError('Failed to delete workspace'),
	});
};
