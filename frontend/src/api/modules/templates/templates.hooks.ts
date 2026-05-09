import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { notify } from '@/api/core';
import type { ITemplateFilters } from '@/types/template.type';
import { TemplateService } from './templates.service';
import { templateKeys } from './templates.keys';

export const useTemplates = (filters?: ITemplateFilters) =>
	useQuery({
		queryKey: templateKeys.list(filters as unknown as Record<string, unknown>),
		queryFn: ({ signal }) => TemplateService.list(filters, signal),
	});

export const useFeaturedTemplates = () =>
	useQuery({
		queryKey: templateKeys.featured(),
		queryFn: ({ signal }) => TemplateService.featured(signal),
	});

export const useTemplateCategories = () =>
	useQuery({
		queryKey: templateKeys.categories(),
		queryFn: ({ signal }) => TemplateService.categories(signal),
	});

export const useTemplate = (id: string) =>
	useQuery({
		queryKey: templateKeys.detail(id),
		queryFn: ({ signal }) => TemplateService.detail(id, signal),
		enabled: !!id,
	});

export const useTrackTemplateView = () =>
	useMutation({
		mutationFn: (id: string) => TemplateService.trackView(id),
	});

export const useUseTemplate = (ws: string) => {
	const qc = useQueryClient();
	return useMutation({
		mutationFn: ({ templateId, workflowName }: { templateId: string; workflowName?: string }) =>
			TemplateService.use(ws, templateId, workflowName),
		onSuccess: () => {
			qc.invalidateQueries({ queryKey: ['workflows', ws] });
			notify.success('Workflow created from template');
		},
		onError: notify.fromError('Failed to use template'),
	});
};
