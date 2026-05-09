import { useMutation, useQuery } from '@tanstack/react-query';
import { notify } from '@/api/core';
import type { TBillingCheckoutDto, TBuyCreditsDto } from '@/types/billing.type';
import { BillingService } from './billing.service';
import { billingKeys } from './billing.keys';

export const useBillingCheckout = (ws: string) =>
	useMutation({
		mutationFn: (body: TBillingCheckoutDto) => BillingService.checkout(ws, body),
		onError: notify.fromError('Failed to start checkout'),
	});

export const useBuyCredits = (ws: string) =>
	useMutation({
		mutationFn: (body: TBuyCreditsDto) => BillingService.buyCredits(ws, body),
		onError: notify.fromError('Failed to start credit purchase'),
	});

export const useBillingPortal = (ws: string, enabled = false) =>
	useQuery({
		queryKey: billingKeys.portal(ws),
		queryFn: ({ signal }) => BillingService.portal(ws, signal),
		enabled: !!ws && enabled,
	});
