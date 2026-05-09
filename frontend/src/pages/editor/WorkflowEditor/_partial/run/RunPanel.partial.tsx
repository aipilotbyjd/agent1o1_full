import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import NodeRunOutput from './NodeRunOutput.partial';
import RunConsole from './RunConsole.partial';
import RunTimeline from './RunTimeline.partial';

const RunPanel = () => {
	const { state, dispatch } = useWorkflowEditor();
	if (!state.ui.runPanelOpen) return null;

	const run = state.run;
	const hasOutput = state.nodes.some((node) => node.data.outputPreview !== undefined);

	return (
		<section className='flex h-full flex-col overflow-y-auto border-t border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950'>
			<div className='flex-shrink-0 border-b border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50'>
				<div className='flex items-center justify-between'>
					<div>
						<div className='text-sm font-black text-zinc-900 dark:text-white'>
							Run Output
						</div>
						<RunTimeline run={run} />
					</div>
					<button
						type='button'
						onClick={() => dispatch({ type: 'TOGGLE_RUN_PANEL' })}
						className='rounded-md border border-zinc-300 px-2 py-1 text-xs text-zinc-600 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-400 dark:hover:text-white'>
						Close
					</button>
				</div>
			</div>

			<div className='flex-1 space-y-4 p-4'>
				{hasOutput && (
					<div>
						<div className='mb-2 text-xs font-black tracking-widest text-zinc-500 uppercase dark:text-zinc-400'>
							Node Outputs
						</div>
						<NodeRunOutput nodes={state.nodes} />
					</div>
				)}
				<div className='flex-1'>
					<div className='mb-2 text-xs font-black tracking-widest text-zinc-500 uppercase dark:text-zinc-400'>
						Logs
					</div>
					<RunConsole logs={run.logs} />
				</div>
			</div>
		</section>
	);
};

export default RunPanel;
