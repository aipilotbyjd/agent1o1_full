/**
 * Auth Types
 * Matches the Laravel 12 backend API responses from docs/frontend/modules/01-authentication.md
 */
import type { TApiResponse } from './api.type';

// User entity — as returned by backend
export type TUser = {
	id: string;
	name: string;
	email: string;
	avatar: string | null;
	email_verified_at: string | null;
	created_at: string;
	updated_at: string;
};

// ─── Request DTOs ────────────────────────────────────────────

// POST /auth/login
export type TLoginDto = {
	email: string;
	password: string;
};

// POST /auth/register
export type TRegisterDto = {
	name: string;
	email: string;
	password: string;
	password_confirmation: string;
};

// POST /auth/forgot-password
export type TForgotPasswordDto = {
	email: string;
};

// POST /auth/reset-password
export type TResetPasswordDto = {
	email: string;
	token: string;
	password: string;
	password_confirmation: string;
};

// PUT /user
export type TUpdateProfileDto = {
	name?: string;
	email?: string;
};

// PUT /user/password
export type TChangePasswordDto = {
	current_password: string;
	password: string;
	password_confirmation: string;
};

// ─── Response Types ──────────────────────────────────────────

// Token object nested inside auth response
export type TAuthToken = {
	token_type: string;
	expires_in: number;
	access_token: string;
	refresh_token: string;
};

// Login / Register response — data payload inside TApiResponse
export type TAuthData = {
	user: TUser;
	token: TAuthToken;
};

// Full auth response from backend
export type TAuthResponse = TApiResponse<TAuthData>;

// Avatar upload response
export type TAvatarResponse = TApiResponse<{ avatar_url: string }>;
