import { toast } from 'react-toastify';
import { ApiError } from './errors';

/**
 * The ONLY place in the API layer where `react-toastify` is imported directly.
 * Hooks and interceptors must call `notify.*` so toasts can be centrally
 * silenced (in tests) or swapped for another UI library later.
 */
export const notify = {
	success: (msg: string) => toast.success(msg),
	error: (msg: string) => toast.error(msg),
	info: (msg: string) => toast.info(msg),
	warn: (msg: string) => toast.warn(msg),

	/**
	 * React Query `onError` helper — shows the ApiError message if available,
	 * otherwise the given fallback.
	 */
	fromError:
		(fallback: string) =>
		(e: unknown): void => {
			toast.error(ApiError.is(e) ? e.message : fallback);
		},
};
