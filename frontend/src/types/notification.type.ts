import type { TListParams } from './api.type';

export type TNotificationType =
	| 'execution_failed'
	| 'execution_succeeded'
	| 'workflow_shared'
	| 'invitation_received'
	| 'credit_low'
	| 'system';

export type TNotification = {
	id: string;
	type: TNotificationType;
	title: string;
	message: string;
	data: Record<string, unknown>;
	read_at: string | null;
	created_at: string;
};

export type TNotificationFilters = TListParams & {
	read?: boolean;
	type?: TNotificationType;
};

export type TUnreadCount = { count: number };

// ── Preferences ──────────────────────────────────────
export type TNotificationPreference = {
	type: TNotificationType;
	in_app: boolean;
	email: boolean;
	push: boolean;
};

export type TUpdateNotificationPreferencesDto = {
	preferences: TNotificationPreference[];
};

// ── Channels ─────────────────────────────────────────
export type TNotificationChannelType = 'slack' | 'discord' | 'webhook' | 'sms';

export type TNotificationChannel = {
	id: string;
	type: TNotificationChannelType;
	name: string;
	config: Record<string, unknown>;
	is_active: boolean;
	created_at: string;
	updated_at: string;
};

export type TCreateNotificationChannelDto = {
	type: TNotificationChannelType;
	name: string;
	config: Record<string, unknown>;
	is_active?: boolean;
};

export type TUpdateNotificationChannelDto = Partial<TCreateNotificationChannelDto>;
