import { Outlet } from 'react-router';
import { Suspense } from 'react';
import Spinner from '@/components/ui/Spinner';

const EditorLayout = () => {
	return (
		<div className='flex h-screen w-screen flex-col overflow-hidden bg-zinc-100 dark:bg-zinc-900'>
			<Suspense
				fallback={
					<div className='flex h-full w-full items-center justify-center'>
						<Spinner className='size-8' />
					</div>
				}>
				<Outlet />
			</Suspense>
		</div>
	);
};

export default EditorLayout;
