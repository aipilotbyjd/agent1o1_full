import { useQuery } from '@tanstack/react-query';
import type { INodeCategoryFilters, INodeTypeFilters } from '@/types/nodeType.type';
import { NodeTypeService } from './node-types.service';
import { nodeTypeKeys } from './node-types.keys';

export const useNodeTypes = (filters?: INodeTypeFilters) =>
	useQuery({
		queryKey: nodeTypeKeys.list(filters as unknown as Record<string, unknown>),
		queryFn: ({ signal }) => NodeTypeService.list(filters, signal),
	});

export const useNodeCategories = (filters?: INodeCategoryFilters) =>
	useQuery({
		queryKey: nodeTypeKeys.categories(filters as unknown as Record<string, unknown>),
		queryFn: ({ signal }) => NodeTypeService.categories(filters, signal),
	});

export const useNodeType = (nodeType: string) =>
	useQuery({
		queryKey: nodeTypeKeys.detail(nodeType),
		queryFn: ({ signal }) => NodeTypeService.detail(nodeType, signal),
		enabled: !!nodeType,
	});

export const useNodeCategory = (id: string) =>
	useQuery({
		queryKey: nodeTypeKeys.categoryDetail(id),
		queryFn: ({ signal }) => NodeTypeService.categoryDetail(id, signal),
		enabled: !!id,
	});
