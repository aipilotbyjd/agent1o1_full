import { axiosClient } from '@/api/client';
import { unwrap } from '@/api/core';
import type { TApiResponse, TMessageResponse } from '@/api/core';
import type {
	TLoginDto,
	TRegisterDto,
	TAuthResponse,
	TForgotPasswordDto,
	TResetPasswordDto,
	TUser,
	TUpdateProfileDto,
	TChangePasswordDto,
	TAvatarResponse,
} from '@/types/auth.type';
import { AuthEndpoints, UserEndpoints } from './auth.endpoints';

export const AuthService = {
	login: (payload: TLoginDto) =>
		axiosClient.post<TAuthResponse>(AuthEndpoints.login, payload).then((r) => r.data),

	register: (payload: TRegisterDto) =>
		axiosClient.post<TAuthResponse>(AuthEndpoints.register, payload).then((r) => r.data),

	logout: () => axiosClient.post(AuthEndpoints.logout).then(() => undefined),

	forgotPassword: (payload: TForgotPasswordDto) =>
		axiosClient
			.post<TMessageResponse>(AuthEndpoints.forgotPassword, payload)
			.then((r) => r.data),

	resetPassword: (payload: TResetPasswordDto) =>
		axiosClient
			.post<TMessageResponse>(AuthEndpoints.resetPassword, payload)
			.then((r) => r.data),
};

export const UserService = {
	fetchMe: (signal?: AbortSignal) =>
		axiosClient.get<TApiResponse<TUser>>(UserEndpoints.me, { signal }).then(unwrap<TUser>),

	updateProfile: (payload: TUpdateProfileDto) =>
		axiosClient.put<TApiResponse<TUser>>(UserEndpoints.update, payload).then(unwrap<TUser>),

	changePassword: (payload: TChangePasswordDto) =>
		axiosClient
			.put<TMessageResponse>(UserEndpoints.changePassword, payload)
			.then((r) => r.data),

	uploadAvatar: async (file: File): Promise<string> => {
		if (!file.type.startsWith('image/')) throw new Error('Avatar must be an image file');
		if (file.size > 5 * 1024 * 1024) throw new Error('Avatar must be smaller than 5MB');

		const formData = new FormData();
		formData.append('avatar', file);
		const { data } = await axiosClient.post<TAvatarResponse>(
			UserEndpoints.uploadAvatar,
			formData,
			{ headers: { 'Content-Type': 'multipart/form-data' } },
		);
		return data.data.avatar_url;
	},

	deleteAvatar: () => axiosClient.delete(UserEndpoints.deleteAvatar).then(() => undefined),
};
