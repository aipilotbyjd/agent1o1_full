import { useReactFlow } from '@xyflow/react';
import { Bot, Command, LayoutGrid, Map, Maximize2, Minus, PanelBottom, Plus } from 'lucide-react';
import { motion } from 'framer-motion';
import type { ReactNode } from 'react';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';

const ToolButton = ({
	title,
	children,
	onClick,
	active,
}: {
	title: string;
	children: ReactNode;
	onClick: () => void;
	active?: boolean;
}) => (
	<button
		type='button'
		title={title}
		aria-label={title}
		onClick={onClick}
		className={[
			'flex h-9 w-9 items-center justify-center rounded-lg transition',
			active
				? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-300/15 dark:text-emerald-100 dark:ring-emerald-300/20'
				: 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-400 dark:hover:bg-white/[0.07] dark:hover:text-white',
		].join(' ')}>
		{children}
	</button>
);

const ActionBar = () => {
	const { state, dispatch } = useWorkflowEditor();
	const reactFlow = useReactFlow();

	return (
		<motion.div
			initial={{ y: 16, opacity: 0 }}
			animate={{ y: 0, opacity: 1 }}
			className='absolute bottom-5 left-1/2 z-10 flex -translate-x-1/2 items-center gap-1 rounded-xl border border-zinc-200 bg-white/90 p-2 shadow-2xl shadow-zinc-200/70 backdrop-blur-xl dark:border-white/10 dark:bg-zinc-950/90 dark:shadow-black/30'>
			<ToolButton title='Zoom out' onClick={() => reactFlow.zoomOut({ duration: 150 })}>
				<Minus size={16} />
			</ToolButton>
			<div className='flex min-w-[3.25rem] items-center justify-center rounded-md bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-700 dark:bg-white/[0.04] dark:text-zinc-300'>
				{Math.round(reactFlow.getZoom() * 100)}%
			</div>
			<ToolButton title='Zoom in' onClick={() => reactFlow.zoomIn({ duration: 150 })}>
				<Plus size={16} />
			</ToolButton>
			<ToolButton
				title='Fit view'
				onClick={() => reactFlow.fitView({ padding: 0.18, duration: 240 })}>
				<Maximize2 size={15} />
			</ToolButton>
			<ToolButton title='Auto-layout' onClick={() => dispatch({ type: 'AUTO_LAYOUT' })}>
				<LayoutGrid size={15} />
			</ToolButton>
			<div className='mx-1 h-6 w-px bg-zinc-200 dark:bg-white/10' />
			<ToolButton
				title='Toggle minimap'
				active={state.ui.miniMapOpen}
				onClick={() => dispatch({ type: 'TOGGLE_MINIMAP' })}>
				<Map size={15} />
			</ToolButton>
			<ToolButton
				title='Command palette'
				onClick={() => dispatch({ type: 'SET_COMMAND_PALETTE', open: true })}>
				<Command size={15} />
			</ToolButton>
			<ToolButton
				title='AI assistant'
				active={state.ui.aiPanelOpen}
				onClick={() => dispatch({ type: 'TOGGLE_AI_PANEL' })}>
				<Bot size={15} />
			</ToolButton>
			<ToolButton
				title='Run console'
				active={state.ui.runPanelOpen}
				onClick={() => dispatch({ type: 'TOGGLE_RUN_PANEL' })}>
				<PanelBottom size={15} />
			</ToolButton>
		</motion.div>
	);
};

export default ActionBar;
