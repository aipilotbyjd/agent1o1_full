export const AuthEndpoints = {
	login: '/auth/login',
	register: '/auth/register',
	logout: '/auth/logout',
	refresh: '/auth/refresh',
	forgotPassword: '/auth/forgot-password',
	resetPassword: '/auth/reset-password',
} as const;

export const UserEndpoints = {
	me: '/user',
	update: '/user',
	changePassword: '/user/password',
	uploadAvatar: '/user/avatar',
	deleteAvatar: '/user/avatar',
} as const;
