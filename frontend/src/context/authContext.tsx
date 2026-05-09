import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Outlet, useNavigate } from 'react-router';
import { clearTokens, getAccessToken, hasValidToken, TOKEN_CHANGE_EVENT } from '@/api/core';
import { useCurrentUser, useLogin, useLogout, useRegister } from '@/api/modules/auth';
import type { TLoginDto, TRegisterDto, TUser } from '@/types/auth.type';

const AUTH_REDIRECT_PATH = '/customer';

export interface IAuthContextProps {
	isLoading: boolean;
	isAuthenticated: boolean;
	userData: TUser | null;
	onLogin: (email: string, password: string, rememberMe: boolean) => Promise<void>;
	onRegister: (data: TRegisterDto, rememberMe?: boolean) => Promise<void>;
	onLogout: (isRedirect: boolean) => Promise<void>;
}
const AuthContext = createContext<IAuthContextProps>({} as IAuthContextProps);

export const AuthProvider = () => {
	const navigate = useNavigate();
	const [accessToken, setAccessToken] = useState<string | null>(() => getAccessToken());
	const hasActiveToken = !!accessToken && hasValidToken();
	const { data: userData, isLoading: isCurrentUserLoading } = useCurrentUser(hasActiveToken);
	const loginMutation = useLogin();
	const registerMutation = useRegister();
	const logoutMutation = useLogout();

	useEffect(() => {
		const syncToken = () => setAccessToken(getAccessToken());

		window.addEventListener(TOKEN_CHANGE_EVENT, syncToken);
		window.addEventListener('storage', syncToken);

		return () => {
			window.removeEventListener(TOKEN_CHANGE_EVENT, syncToken);
			window.removeEventListener('storage', syncToken);
		};
	}, []);

	const onLogin = useCallback(
		async (email: string, password: string, rememberMe: boolean) => {
			const credentials: TLoginDto = { email, password };
			await loginMutation.mutateAsync({ ...credentials, rememberMe });
			setAccessToken(getAccessToken());
			navigate(AUTH_REDIRECT_PATH, { replace: true });
		},
		[loginMutation, navigate],
	);

	const onRegister = useCallback(
		async (data: TRegisterDto) => {
			await registerMutation.mutateAsync(data);
			setAccessToken(getAccessToken());
			navigate(AUTH_REDIRECT_PATH, { replace: true });
		},
		[registerMutation, navigate],
	);

	const onLogout = useCallback(
		async (isNavigate = true) => {
			try {
				if (accessToken) await logoutMutation.mutateAsync();
				else clearTokens();
			} finally {
				setAccessToken(getAccessToken());
				if (isNavigate) navigate('/login', { replace: true });
			}
		},
		[accessToken, logoutMutation, navigate],
	);

	const isLoading =
		isCurrentUserLoading ||
		loginMutation.isPending ||
		registerMutation.isPending ||
		logoutMutation.isPending;
	const isAuthenticated = hasActiveToken && !!userData;

	const value: IAuthContextProps = useMemo(
		() => ({
			isLoading,
			isAuthenticated,
			onLogout,
			onLogin,
			onRegister,
			userData: userData ?? null,
		}),
		[isLoading, isAuthenticated, onLogout, onLogin, onRegister, userData],
	);
	return (
		<AuthContext.Provider value={value}>
			<Outlet />
		</AuthContext.Provider>
	);
};

// eslint-disable-next-line react-refresh/only-export-components
export const useAuth = () => {
	return useContext(AuthContext);
};
