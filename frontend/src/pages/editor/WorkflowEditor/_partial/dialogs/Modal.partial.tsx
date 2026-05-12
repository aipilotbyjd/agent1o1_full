import { X } from 'lucide-react';
import { motion } from 'framer-motion';
import type { ReactNode } from 'react';

const Modal = ({
	title,
	children,
	onClose,
}: {
	title: string;
	children: ReactNode;
	onClose: () => void;
}) => (
	<div className='fixed inset-0 z-50 flex items-center justify-center bg-black/65 p-4 backdrop-blur-sm'>
		<motion.div
			initial={{ opacity: 0, scale: 0.96, y: 10 }}
			animate={{ opacity: 1, scale: 1, y: 0 }}
			transition={{ duration: 0.16 }}
			className='w-full max-w-2xl overflow-hidden rounded-2xl border border-white/10 bg-zinc-950/96 text-zinc-100 shadow-2xl shadow-black/50 backdrop-blur-xl'>
			<div className='flex items-center justify-between border-b border-white/10 px-5 py-4'>
				<div className='text-sm font-semibold tracking-[0.16em] text-zinc-400 uppercase'>
					{title}
				</div>
				<button
					type='button'
					onClick={onClose}
					className='flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 text-zinc-500 hover:bg-white/[0.06] hover:text-white'>
					<X size={14} />
				</button>
			</div>
			<div className='p-5'>{children}</div>
		</motion.div>
	</div>
);

export default Modal;
