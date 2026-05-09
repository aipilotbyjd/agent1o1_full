export const AgentEndpoints = {
	list: (ws: string) => `/workspaces/${ws}/agents`,
	create: (ws: string) => `/workspaces/${ws}/agents`,
	detail: (ws: string, agentId: string) => `/workspaces/${ws}/agents/${agentId}`,
	update: (ws: string, agentId: string) => `/workspaces/${ws}/agents/${agentId}`,
	delete: (ws: string, agentId: string) => `/workspaces/${ws}/agents/${agentId}`,
	duplicate: (ws: string, agentId: string) => `/workspaces/${ws}/agents/${agentId}/duplicate`,
	attachSkill: (ws: string, agentId: string) =>
		`/workspaces/${ws}/agents/${agentId}/skills/attach`,
	detachSkill: (ws: string, agentId: string, skillId: string) =>
		`/workspaces/${ws}/agents/${agentId}/skills/${skillId}`,
	conversations: (ws: string, agentId: string) =>
		`/workspaces/${ws}/agents/${agentId}/conversations`,
} as const;

export const AgentSkillEndpoints = {
	list: (ws: string) => `/workspaces/${ws}/agent-skills`,
	create: (ws: string) => `/workspaces/${ws}/agent-skills`,
	update: (ws: string, skillId: string) => `/workspaces/${ws}/agent-skills/${skillId}`,
	delete: (ws: string, skillId: string) => `/workspaces/${ws}/agent-skills/${skillId}`,
} as const;
