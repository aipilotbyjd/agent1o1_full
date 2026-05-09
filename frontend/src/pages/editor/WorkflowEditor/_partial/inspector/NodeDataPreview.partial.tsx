import type { TCanvasNode } from '../../_types/canvas.type';

const NodeDataPreview = ({ node }: { node: TCanvasNode }) => (
	<div>
		<h3 className='mb-3 text-xs font-black tracking-widest text-zinc-500 uppercase dark:text-zinc-400'>
			Data Preview
		</h3>
		<pre
			className='max-h-60 overflow-auto rounded-lg bg-zinc-100 p-3 text-xs text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300'
			aria-label='Node data output preview'>
			{JSON.stringify(node.data.outputPreview ?? node.data.values, null, 2)}
		</pre>
	</div>
);

export default NodeDataPreview;
