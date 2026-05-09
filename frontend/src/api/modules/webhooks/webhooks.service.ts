import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse } from '@/api/core';
import type { TWebhook, TWebhookLog } from '@/types/webhook.type';
import { WebhookEndpoints as E } from './webhooks.endpoints';

export const WebhookService = {
	list: (ws: string, signal?: AbortSignal) =>
		axiosClient.get<TApiResponse<TWebhook[]>>(E.list(ws), { signal }).then(unwrap<TWebhook[]>),

	detail: (ws: string, webhookId: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TWebhook>>(E.detail(ws, webhookId), { signal })
			.then(unwrap<TWebhook>),

	create: (ws: string, body: Partial<TWebhook>) =>
		axiosClient.post<TApiResponse<TWebhook>>(E.create(ws), body).then(unwrap<TWebhook>),

	update: (ws: string, webhookId: string, body: Partial<TWebhook>) =>
		axiosClient
			.put<TApiResponse<TWebhook>>(E.update(ws, webhookId), body)
			.then(unwrap<TWebhook>),

	remove: (ws: string, webhookId: string) =>
		axiosClient.delete(E.delete(ws, webhookId)).then(() => undefined),

	activate: (ws: string, webhookId: string) =>
		axiosClient.post(E.activate(ws, webhookId)).then(() => undefined),

	deactivate: (ws: string, webhookId: string) =>
		axiosClient.post(E.deactivate(ws, webhookId)).then(() => undefined),

	test: (ws: string, webhookId: string, body: unknown) =>
		axiosClient.post(E.test(ws, webhookId), body).then(() => undefined),

	listLogs: (ws: string, webhookId: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TWebhookLog[]>>(E.logs(ws, webhookId), { signal })
			.then(unwrap<TWebhookLog[]>),

	getLog: (ws: string, webhookId: string, logId: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TWebhookLog>>(E.logDetail(ws, webhookId, logId), { signal })
			.then(unwrap<TWebhookLog>),
};
