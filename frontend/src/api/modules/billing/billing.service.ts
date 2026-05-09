import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse } from '@/api/core';
import type {
	TBillingCheckoutDto,
	TBuyCreditsDto,
	TBillingCheckoutResponse,
	TBillingPortalResponse,
} from '@/types/billing.type';
import { BillingEndpoints as E } from './billing.endpoints';

export const BillingService = {
	checkout: (ws: string, body: TBillingCheckoutDto) =>
		axiosClient
			.post<TApiResponse<TBillingCheckoutResponse>>(E.checkout(ws), body)
			.then(unwrap<TBillingCheckoutResponse>),

	buyCredits: (ws: string, body: TBuyCreditsDto) =>
		axiosClient
			.post<TApiResponse<TBillingCheckoutResponse>>(E.buyCredits(ws), body)
			.then(unwrap<TBillingCheckoutResponse>),

	portal: (ws: string, signal?: AbortSignal) =>
		axiosClient
			.get<TApiResponse<TBillingPortalResponse>>(E.portal(ws), { signal })
			.then(unwrap<TBillingPortalResponse>),
};
