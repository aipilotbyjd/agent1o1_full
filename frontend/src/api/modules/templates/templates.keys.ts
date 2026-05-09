import type { TListParams } from '@/api/core';

export const templateKeys = {
	all: () => ['templates'] as const,
	list: (params?: TListParams) => ['templates', 'list', params] as const,
	featured: () => ['templates', 'featured'] as const,
	categories: () => ['templates', 'categories'] as const,
	detail: (id: string) => ['templates', 'detail', id] as const,
};
