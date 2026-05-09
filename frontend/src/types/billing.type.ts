export type TBillingCheckoutDto = {
	plan_id: string;
	billing_cycle?: 'monthly' | 'yearly';
	success_url?: string;
	cancel_url?: string;
};

export type TBuyCreditsDto = {
	pack_id?: string;
	amount?: number;
	success_url?: string;
	cancel_url?: string;
};

export type TBillingCheckoutResponse = {
	checkout_url: string;
	session_id: string;
};

export type TBillingPortalResponse = {
	portal_url: string;
};
