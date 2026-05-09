import axios from 'axios';
import { attachAuth } from './interceptors/attach-auth';
import { refreshOn401 } from './interceptors/refresh-token';
import { normalizeError } from './interceptors/normalize-error';

const BASE_URL = import.meta.env.VITE_API_URL || 'https://agent1o1.test/api/v1';

export const axiosClient = axios.create({
	baseURL: BASE_URL,
	timeout: 30_000,
	headers: {
		'Content-Type': 'application/json',
		Accept: 'application/json',
	},
});

attachAuth(axiosClient);
refreshOn401(axiosClient);
normalizeError(axiosClient);

export default axiosClient;
