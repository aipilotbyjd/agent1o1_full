export type TAgent = {
	id: string;
	name: string;
	description: string;
	model: string;
	system_prompt: string;
	temperature: number;
	max_tokens?: number;
	is_active: boolean;
	skills_count: number;
	conversations_count: number;
	skills?: TAgentSkill[];
	created_at: number;
	updated_at: number;
};

export type TAgentSkill = {
	id: string;
	name: string;
	type: 'api_call' | 'vector_search' | 'workflow' | 'script';
	description: string;
	config: Record<string, unknown>;
	created_at: number;
};

export type TAgentConversation = {
	id: string;
	session_id: string;
	messages_count: number;
	started_at: number;
	last_message_at: number;
};

export type TAgentSortBy = 'name' | 'created_at' | 'updated_at' | 'conversations_count';
export type TSortOrder = 'asc' | 'desc';
