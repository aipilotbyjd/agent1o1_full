import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse } from '@/api/core';
import type {
	INodeType,
	INodeCategory,
	INodeCategoryFilters,
	INodeTypeFilters,
} from '@/types/nodeType.type';
import { NodeTypeEndpoints as E } from './node-types.endpoints';

export const NodeTypeService = {
	list: (filters?: INodeTypeFilters, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<INodeType[]>>(E.list, { params: filters, signal })
			.then(unwrap<INodeType[]>),

	categories: (filters?: INodeCategoryFilters, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<INodeCategory[]>>(E.categories, { params: filters, signal })
			.then(unwrap<INodeCategory[]>),

	detail: (nodeType: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<INodeType>>(E.detail(nodeType), { signal })
			.then(unwrap<INodeType>),

	categoryDetail: (id: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<INodeCategory>>(E.categoryDetail(id), { signal })
			.then(unwrap<INodeCategory>),
};
