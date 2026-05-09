import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse } from '@/api/core';
import type {
	ICredential,
	ICredentialDetail,
	ICreateCredentialDto,
	IUpdateCredentialDto,
	IShareCredentialDto,
	IUpdateSharingScopeDto,
	ICredentialFilters,
	IOAuthProvider,
	IOAuthAuthResponse,
	IStartOAuthDto,
} from '@/types/credential.type';
import { CredentialEndpoints as E, OAuthEndpoints as O } from './credentials.endpoints';

const buildOAuthAuthorizeParams = (params: IStartOAuthDto): string => {
	const sp = new URLSearchParams();
	if (params.credentialName) sp.set('credential_name', params.credentialName);
	if (params.redirectUrl) sp.set('redirect_url', params.redirectUrl);
	if (params.sharingScope) sp.set('sharing_scope', params.sharingScope);
	if (params.userIds?.length) sp.set('user_ids', params.userIds.join(','));
	return sp.toString();
};

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

	refreshToken: (ws: string, id: string) =>
		axiosClient.post<TApiResponse<ICredential>>(E.refresh(ws, id)).then(unwrap<ICredential>),

	share: (ws: string, id: string, body: IShareCredentialDto) =>
		axiosClient.post(E.share(ws, id), body).then(() => undefined),

	unshare: (ws: string, id: string, userId: string) =>
		axiosClient.delete(E.unshare(ws, id, userId)).then(() => undefined),

	updateSharingScope: (ws: string, id: string, body: IUpdateSharingScopeDto) =>
		axiosClient
			.put<TApiResponse<ICredential>>(E.sharingScope(ws, id), body)
			.then(unwrap<ICredential>),
};

export const OAuthService = {
	providers: (signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<IOAuthProvider[]>>(O.providers, { signal })
			.then(unwrap<IOAuthProvider[]>),

	getAuthorizeUrl: (ws: string, params: string | IStartOAuthDto) => {
		const normalized = typeof params === 'string' ? { provider: params } : { ...params };
		const qs = buildOAuthAuthorizeParams(normalized);
		return axiosClient
			.get<
				TApiResponse<IOAuthAuthResponse>
			>(`${O.authorize(ws, normalized.provider)}${qs ? `?${qs}` : ''}`)
			.then(unwrap<IOAuthAuthResponse>);
	},
};
