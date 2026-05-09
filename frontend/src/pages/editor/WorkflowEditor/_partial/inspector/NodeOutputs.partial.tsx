import { PORT_TYPE_COLOR } from '../../_helper/builder.constants';
import type { TNodeDefinition } from '../../_types/node.type';

const NodeOutputs = ({ def }: { def: TNodeDefinition }) => (
	<div>
		<div className='mb-3 flex items-baseline justify-between'>
			<h3 className='text-xs font-black tracking-widest text-zinc-500 uppercase dark:text-zinc-400'>
				Outputs
			</h3>
			<span className='text-xs text-zinc-500 dark:text-zinc-400'>
				{def.outputs.length} port{def.outputs.length !== 1 ? 's' : ''}
			</span>
		</div>
		<div className='space-y-1.5' role='list'>
			{def.outputs.length ? (
				def.outputs.map((output) => (
					<div
						key={output.id}
						role='listitem'
						className='flex items-center justify-between rounded-lg bg-zinc-100 px-3 py-2.5 text-sm text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100'>
						<span className='font-medium'>{output.name}</span>
						<span className='flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400'>
							<span
								className='h-2 w-2 rounded-full'
								style={{ backgroundColor: PORT_TYPE_COLOR[output.type] }}
								title={output.type}
							/>
							<span>{output.type}</span>
						</span>
					</div>
				))
			) : (
				<div className='rounded-lg bg-zinc-100 px-3 py-6 text-center text-sm text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400'>
					No outputs configured
				</div>
			)}
		</div>
	</div>
);

export default NodeOutputs;
