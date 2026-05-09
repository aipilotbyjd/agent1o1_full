export const agentKeys = {
	all: (ws: string) => ['agents', ws] as const,
	list: (ws: string) => ['agents', ws, 'list'] as const,
	detail: (ws: string, agentId: string) => ['agents', ws, 'detail', agentId] as const,
	conversations: (ws: string, agentId: string) =>
		['agents', ws, agentId, 'conversations'] as const,
};

export const agentSkillKeys = {
	all: (ws: string) => ['agent-skills', ws] as const,
	list: (ws: string) => ['agent-skills', ws, 'list'] as const,
};
