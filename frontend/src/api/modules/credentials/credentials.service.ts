import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse } from '@/api/core';
import type {
	ICredential,
	ICredentialDetail,
	ICreateCredentialDto,
	IUpdateCredentialDto,
	ICredentialFilters,
	IOAuthAuthResponse,
	IStartOAuthDto,
} from '@/types/credential.type';
import { CredentialEndpoints as E, OAuthEndpoints as O } from './credentials.endpoints';

export const CredentialService = {
	list: (ws: string, filters?: ICredentialFilters, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<ICredential[]>>(E.list(ws), { params: filters, signal })
			.then(unwrap<ICredential[]>),

	detail: (ws: string, id: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<ICredentialDetail>>(E.detail(ws, id), { signal })
			.then(unwrap<ICredentialDetail>),

	create: (ws: string, body: ICreateCredentialDto) =>
		axiosClient.post<TApiResponse<ICredential>>(E.create(ws), body).then(unwrap<ICredential>),

	update: (ws: string, id: string, body: IUpdateCredentialDto) =>
		axiosClient
			.put<TApiResponse<ICredential>>(E.update(ws, id), body)
			.then(unwrap<ICredential>),

	remove: (ws: string, id: string) => axiosClient.delete(E.delete(ws, id)).then(() => undefined),

	test: (ws: string, id: string) =>
		axiosClient
			.post<TApiResponse<{ success: boolean; message: string }>>(E.test(ws, id))
			.then(unwrap<{ success: boolean; message: string }>),
};

export const OAuthService = {
	initiate: (ws: string, body: IStartOAuthDto) =>
		axiosClient
			.post<TApiResponse<IOAuthAuthResponse>>(O.initiate(ws), body)
			.then(unwrap<IOAuthAuthResponse>),
};
