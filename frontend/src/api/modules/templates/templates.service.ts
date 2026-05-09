import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse } from '@/api/core';
import type {
	ITemplate,
	ITemplateDetail,
	ITemplateFilters,
	TTemplateCategory,
} from '@/types/template.type';
import { TemplateEndpoints as E } from './templates.endpoints';

export const TemplateService = {
	list: (filters?: ITemplateFilters, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<ITemplate[]>>(E.list(), { params: filters, signal })
			.then(unwrap<ITemplate[]>),

	detail: (id: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<ITemplateDetail>>(E.detail(id), { signal })
			.then(unwrap<ITemplateDetail>),

	use: (ws: string, templateId: string, workflowName?: string) =>
		axiosClient
			.post<
				TApiResponse<{ workflow_id: string }>
			>(E.use(ws, templateId), workflowName ? { workflow_name: workflowName } : undefined)
			.then(unwrap<{ workflow_id: string }>),
};
