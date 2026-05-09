import { useReactFlow } from '@xyflow/react';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import Icon from '@/components/icon/Icon';

const ToolButton = ({
	title,
	icon,
	onClick,
	active,
}: {
	title: string;
	icon: string;
	onClick: () => void;
	active?: boolean;
}) => (
	<button
		type='button'
		title={title}
		aria-label={title}
		onClick={onClick}
		className={[
			'flex h-9 w-9 items-center justify-center rounded-lg text-lg transition',
			active
				? 'bg-emerald-500 text-white'
				: 'text-zinc-300 hover:bg-zinc-800 hover:text-white',
		].join(' ')}>
		<Icon icon={icon} />
	</button>
);

const ActionBar = () => {
	const { state, dispatch } = useWorkflowEditor();
	const reactFlow = useReactFlow();

	return (
		<div className='absolute bottom-4 left-1/2 z-10 flex -translate-x-1/2 items-center gap-1 rounded-xl border border-white/10 bg-zinc-950/90 p-2 shadow-xl backdrop-blur'>
			<ToolButton
				title='Zoom out'
				icon='ZoomOutArea'
				onClick={() => reactFlow.zoomOut({ duration: 150 })}
			/>
			<div className='flex min-w-[3rem] items-center justify-center text-xs font-bold text-zinc-300'>
				{Math.round(reactFlow.getZoom() * 100)}%
			</div>
			<ToolButton
				title='Zoom in'
				icon='ZoomInArea'
				onClick={() => reactFlow.zoomIn({ duration: 150 })}
			/>
			<ToolButton
				title='Fit view'
				icon='FitToScreen'
				onClick={() => reactFlow.fitView({ padding: 0.18, duration: 240 })}
			/>
			<ToolButton
				title='Auto-layout'
				icon='LayoutGrid'
				onClick={() => dispatch({ type: 'AUTO_LAYOUT' })}
			/>
			<div className='mx-1 h-6 w-px bg-white/10' />
			<ToolButton
				title='Toggle minimap'
				icon='Maps'
				active={state.ui.miniMapOpen}
				onClick={() => dispatch({ type: 'TOGGLE_MINIMAP' })}
			/>
			<ToolButton
				title='Command palette'
				icon='Command'
				onClick={() => dispatch({ type: 'SET_COMMAND_PALETTE', open: true })}
			/>
			<ToolButton
				title='Run console'
				icon='CommandLine'
				active={state.ui.runPanelOpen}
				onClick={() => dispatch({ type: 'TOGGLE_RUN_PANEL' })}
			/>
		</div>
	);
};

export default ActionBar;
