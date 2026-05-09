import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { notify } from '@/api/core';
import type { TAgent, TAgentSkill } from '@/types/agent.type';
import { AgentService, AgentSkillService } from './agents.service';
import { agentKeys, agentSkillKeys } from './agents.keys';

// ── Agents ───────────────────────────────────────────
export const useAgents = (ws: string) =>
	useQuery({
		queryKey: agentKeys.list(ws),
		queryFn: ({ signal }) => AgentService.list(ws, signal),
		enabled: !!ws,
	});

export const useAgent = (ws: string, agentId: string) =>
	useQuery({
		queryKey: agentKeys.detail(ws, agentId),
		queryFn: ({ signal }) => AgentService.detail(ws, agentId, signal),
		enabled: !!ws && !!agentId,
	});

export const useAgentConversations = (ws: string, agentId: string) =>
	useQuery({
		queryKey: agentKeys.conversations(ws, agentId),
		queryFn: ({ signal }) => AgentService.listConversations(ws, agentId, signal),
		enabled: !!ws && !!agentId,
	});

export const useCreateAgent = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: (body: Partial<TAgent>) => AgentService.create(ws, body),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: agentKeys.all(ws) });
			notify.success('Agent created');
		},
		onError: notify.fromError('Failed to create agent'),
	});
};

export const useUpdateAgent = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: ({ agentId, body }: { agentId: string; body: Partial<TAgent> }) =>
			AgentService.update(ws, agentId, body),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: agentKeys.all(ws) });
			notify.success('Agent updated');
		},
		onError: notify.fromError('Failed to update agent'),
	});
};

export const useDeleteAgent = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: (agentId: string) => AgentService.remove(ws, agentId),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: agentKeys.all(ws) });
			notify.success('Agent deleted');
		},
		onError: notify.fromError('Failed to delete agent'),
	});
};

export const useDuplicateAgent = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: (agentId: string) => AgentService.duplicate(ws, agentId),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: agentKeys.all(ws) });
			notify.success('Agent duplicated');
		},
		onError: notify.fromError('Failed to duplicate agent'),
	});
};

export const useAttachAgentSkill = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: ({ agentId, skillId }: { agentId: string; skillId: string }) =>
			AgentService.attachSkill(ws, agentId, skillId),
		onSuccess: (_d, { agentId }) => {
			qc.invalidateQueries({ queryKey: agentKeys.detail(ws, agentId) });
			notify.success('Skill attached');
		},
		onError: notify.fromError('Failed to attach skill'),
	});
};

export const useDetachAgentSkill = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: ({ agentId, skillId }: { agentId: string; skillId: string }) =>
			AgentService.detachSkill(ws, agentId, skillId),
		onSuccess: (_d, { agentId }) => {
			qc.invalidateQueries({ queryKey: agentKeys.detail(ws, agentId) });
			notify.success('Skill detached');
		},
		onError: notify.fromError('Failed to detach skill'),
	});
};

// ── Skills ───────────────────────────────────────────
export const useAgentSkills = (ws: string) =>
	useQuery({
		queryKey: agentSkillKeys.list(ws),
		queryFn: ({ signal }) => AgentSkillService.list(ws, signal),
		enabled: !!ws,
	});

export const useCreateAgentSkill = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: (body: Partial<TAgentSkill>) => AgentSkillService.create(ws, body),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: agentSkillKeys.all(ws) });
			notify.success('Skill created');
		},
		onError: notify.fromError('Failed to create skill'),
	});
};

export const useUpdateAgentSkill = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: ({ skillId, body }: { skillId: string; body: Partial<TAgentSkill> }) =>
			AgentSkillService.update(ws, skillId, body),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: agentSkillKeys.all(ws) });
			notify.success('Skill updated');
		},
		onError: notify.fromError('Failed to update skill'),
	});
};

export const useDeleteAgentSkill = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: (skillId: string) => AgentSkillService.remove(ws, skillId),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: agentSkillKeys.all(ws) });
			notify.success('Skill deleted');
		},
		onError: notify.fromError('Failed to delete skill'),
	});
};
