import { ReactNode } from 'react';

const Modal = ({
	title,
	children,
	onClose,
}: {
	title: string;
	children: ReactNode;
	onClose: () => void;
}) => (
	<div className='fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm'>
		<div className='w-full max-w-2xl overflow-hidden rounded-2xl border border-zinc-300 bg-white text-zinc-900 shadow-2xl dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100'>
			<div className='flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800'>
				<div className='text-sm font-black tracking-widest text-zinc-900 uppercase dark:text-white'>
					{title}
				</div>
				<button
					type='button'
					onClick={onClose}
					className='rounded-lg border border-zinc-300 px-2 py-1 text-xs text-zinc-600 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-400 dark:hover:text-white'>
					Close
				</button>
			</div>
			<div className='p-5'>{children}</div>
		</div>
	</div>
);

export default Modal;
