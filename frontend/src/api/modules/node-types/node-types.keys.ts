import type { TListParams } from '@/api/core';

export const nodeTypeKeys = {
	all: () => ['nodeTypes'] as const,
	list: (params?: TListParams) => ['nodeTypes', 'list', params] as const,
	categories: (params?: TListParams) => ['nodeTypes', 'categories', params] as const,
	categoryDetail: (id: string) => ['nodeTypes', 'categories', 'detail', id] as const,
	detail: (nodeType: string) => ['nodeTypes', 'detail', nodeType] as const,
};
