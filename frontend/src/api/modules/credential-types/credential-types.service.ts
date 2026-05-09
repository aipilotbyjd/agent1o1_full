import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse } from '@/api/core';
import type { TCredentialType, TCredentialTypeFilters } from '@/types/credentialType.type';
import { CredentialTypeEndpoints as E } from './credential-types.endpoints';

export const CredentialTypeService = {
	list: (filters?: TCredentialTypeFilters, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TCredentialType[]>>(E.list, { params: filters, signal })
			.then(unwrap<TCredentialType[]>),

	detail: (id: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TCredentialType>>(E.detail(id), { signal })
			.then(unwrap<TCredentialType>),
};
