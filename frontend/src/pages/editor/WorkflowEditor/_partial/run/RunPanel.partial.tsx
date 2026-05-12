import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import { X } from 'lucide-react';
import NodeRunOutput from './NodeRunOutput.partial';
import RunConsole from './RunConsole.partial';
import RunTimeline from './RunTimeline.partial';

const RunPanel = () => {
	const { state, dispatch } = useWorkflowEditor();
	if (!state.ui.runPanelOpen) return null;

	const run = state.run;
	const hasOutput = state.nodes.some((node) => node.data.outputPreview !== undefined);

	return (
		<section className='flex h-full flex-col overflow-y-auto border-t border-white/10 bg-zinc-950 text-zinc-100'>
			<div className='flex-shrink-0 border-b border-white/10 bg-white/[0.025] p-4'>
				<div className='flex items-center justify-between'>
					<div>
						<div className='text-sm font-semibold text-white'>Execution console</div>
						<RunTimeline run={run} />
					</div>
					<button
						type='button'
						onClick={() => dispatch({ type: 'TOGGLE_RUN_PANEL' })}
						className='flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 text-zinc-500 hover:bg-white/[0.06] hover:text-white'>
						<X size={14} />
					</button>
				</div>
			</div>

			<div className='flex-1 space-y-4 p-4'>
				{hasOutput && (
					<div>
						<div className='mb-2 text-xs font-semibold tracking-[0.16em] text-zinc-600 uppercase'>
							Node Outputs
						</div>
						<NodeRunOutput nodes={state.nodes} />
					</div>
				)}
				<div className='flex-1'>
					<div className='mb-2 text-xs font-semibold tracking-[0.16em] text-zinc-600 uppercase'>
						Logs
					</div>
					<RunConsole logs={run.logs} />
				</div>
			</div>
		</section>
	);
};

export default RunPanel;
