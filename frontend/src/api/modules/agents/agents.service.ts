import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse } from '@/api/core';
import type { TAgent, TAgentSkill, TAgentConversation } from '@/types/agent.type';
import { AgentEndpoints as E, AgentSkillEndpoints as S } from './agents.endpoints';

export const AgentService = {
	list: (ws: string, signal?: AbortSignal) =>
		axiosClient.get<TApiResponse<TAgent[]>>(E.list(ws), { signal }).then(unwrap<TAgent[]>),

	detail: (ws: string, agentId: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TAgent>>(E.detail(ws, agentId), { signal })
			.then(unwrap<TAgent>),

	create: (ws: string, body: Partial<TAgent>) =>
		axiosClient.post<TApiResponse<TAgent>>(E.create(ws), body).then(unwrap<TAgent>),

	update: (ws: string, agentId: string, body: Partial<TAgent>) =>
		axiosClient.put<TApiResponse<TAgent>>(E.update(ws, agentId), body).then(unwrap<TAgent>),

	remove: (ws: string, agentId: string) =>
		axiosClient.delete(E.delete(ws, agentId)).then(() => undefined),

	duplicate: (ws: string, agentId: string) =>
		axiosClient.post<TApiResponse<TAgent>>(E.duplicate(ws, agentId)).then(unwrap<TAgent>),

	attachSkill: (ws: string, agentId: string, skillId: string) =>
		axiosClient.post(E.attachSkill(ws, agentId), { skill_id: skillId }).then(() => undefined),

	detachSkill: (ws: string, agentId: string, skillId: string) =>
		axiosClient.delete(E.detachSkill(ws, agentId, skillId)).then(() => undefined),

	listConversations: (ws: string, agentId: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TAgentConversation[]>>(E.conversations(ws, agentId), { signal })
			.then(unwrap<TAgentConversation[]>),
};

export const AgentSkillService = {
	list: (ws: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TAgentSkill[]>>(S.list(ws), { signal })
			.then(unwrap<TAgentSkill[]>),

	create: (ws: string, body: Partial<TAgentSkill>) =>
		axiosClient.post<TApiResponse<TAgentSkill>>(S.create(ws), body).then(unwrap<TAgentSkill>),

	update: (ws: string, skillId: string, body: Partial<TAgentSkill>) =>
		axiosClient
			.put<TApiResponse<TAgentSkill>>(S.update(ws, skillId), body)
			.then(unwrap<TAgentSkill>),

	remove: (ws: string, skillId: string) =>
		axiosClient.delete(S.delete(ws, skillId)).then(() => undefined),
};
