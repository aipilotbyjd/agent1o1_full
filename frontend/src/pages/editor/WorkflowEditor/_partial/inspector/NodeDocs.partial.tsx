import type { TNodeDefinition } from '../../_types/node.type';

const NodeDocs = ({ def }: { def: TNodeDefinition }) => (
	<div className='rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950'>
		<h3 className='text-xs font-black tracking-widest text-zinc-500 uppercase dark:text-zinc-400'>
			Documentation
		</h3>
		<p className='mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300'>
			{def.description}
		</p>
		{def.supportsLoopMode && (
			<div className='mt-3 rounded-lg border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-800/50 dark:bg-indigo-950/30'>
				<p className='text-sm leading-relaxed text-indigo-600 dark:text-indigo-300'>
					<span className='font-semibold'>Loop Mode:</span> This node is designed for list
					processing and can be wired after list-producing steps.
				</p>
			</div>
		)}
		{def.requiresCredential && (
			<div className='mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800/50 dark:bg-amber-950/30'>
				<p className='text-sm leading-relaxed text-amber-600 dark:text-amber-300'>
					<span className='font-semibold'>Credential Required:</span> This node requires a
					connected credential before production execution.
				</p>
			</div>
		)}
	</div>
);

export default NodeDocs;
