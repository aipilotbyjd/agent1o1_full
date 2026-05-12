import {
	Bot,
	CheckCircle2,
	Cloud,
	PanelLeft,
	Play,
	Rocket,
	RotateCcw,
	RotateCw,
	Settings2,
	Moon,
	Square,
	Sun,
	UploadCloud,
} from 'lucide-react';
import { motion } from 'framer-motion';
import type { ReactNode } from 'react';
import DARK_MODE from '@/constants/darkMode.constant';
import useDarkMode from '@/hooks/useDarkMode';
import { useCreateWorkflowVersion, usePublishWorkflowVersion } from '@/api/modules/workflows';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import { buildVersionPayload } from '../../_helper/workflowApiTransform.helper';
import { useRunWorkflow } from '../../_hooks/useRunWorkflow.hook';

const IconButton = ({
	title,
	children,
	onClick,
	disabled,
	active,
}: {
	title: string;
	children: ReactNode;
	onClick?: () => void;
	disabled?: boolean;
	active?: boolean;
}) => (
	<button
		type='button'
		title={title}
		aria-label={title}
		onClick={onClick}
		disabled={disabled}
		className={[
			'flex h-9 w-9 items-center justify-center rounded-lg border text-sm transition',
			active
				? 'border-emerald-300/40 bg-emerald-50 text-emerald-700 dark:border-emerald-300/30 dark:bg-emerald-400/15 dark:text-emerald-200'
				: 'border-zinc-200 bg-white text-zinc-500 hover:bg-zinc-100 hover:text-zinc-950 dark:border-white/10 dark:bg-white/[0.03] dark:text-zinc-400 dark:hover:bg-white/[0.07] dark:hover:text-white',
			disabled ? 'cursor-not-allowed opacity-30' : '',
		].join(' ')}>
		{children}
	</button>
);

const PillButton = ({
	children,
	onClick,
	disabled,
	variant = 'ghost',
}: {
	children: ReactNode;
	onClick?: () => void;
	disabled?: boolean;
	variant?: 'ghost' | 'primary' | 'publish';
}) => (
	<button
		type='button'
		onClick={onClick}
		disabled={disabled}
		className={[
			'flex h-9 items-center gap-2 rounded-lg px-3 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-45',
			variant === 'primary'
				? 'border border-emerald-200 bg-emerald-50 text-emerald-700 shadow-lg shadow-emerald-100/50 hover:bg-emerald-100 dark:border-emerald-300/25 dark:bg-emerald-400/15 dark:text-emerald-100 dark:shadow-emerald-950/20 dark:hover:bg-emerald-400/20'
				: variant === 'publish'
					? 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-300/20 dark:bg-emerald-400/15 dark:text-emerald-100 dark:hover:bg-emerald-400/20'
					: 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-100 hover:text-zinc-950 dark:border-white/10 dark:bg-white/[0.03] dark:text-zinc-300 dark:hover:bg-white/[0.07] dark:hover:text-white',
		].join(' ')}>
		{children}
	</button>
);

const savingCopy = {
	saved: 'Autosaved',
	saving: 'Saving...',
	dirty: 'Unsaved draft',
	error: 'Save failed',
};

const Topbar = () => {
	const { state, dispatch } = useWorkflowEditor();
	const { isDarkTheme, setDarkModeStatus } = useDarkMode();
	const { runWorkflow, stopRun } = useRunWorkflow();
	const saveVersion = useCreateWorkflowVersion(state.workflow.workspaceId ?? '');
	const publishVersion = usePublishWorkflowVersion(state.workflow.workspaceId ?? '');
	const isRunning = state.run.status === 'running';
	const canUseWorkflowApi = Boolean(state.workflow.workspaceId && state.workflow.apiId);
	const canPublish = canUseWorkflowApi && Boolean(state.workflow.currentVersionId);

	const handleSave = () => {
		if (!state.workflow.workspaceId || !state.workflow.apiId) {
			dispatch({ type: 'SET_SAVE_STATE', savingState: 'dirty' });
			return;
		}

		dispatch({ type: 'SET_SAVE_STATE', savingState: 'saving' });
		saveVersion.mutate(
			{
				id: state.workflow.apiId,
				body: buildVersionPayload(state),
			},
			{
				onSuccess: (version) => {
					dispatch({
						type: 'SET_WORKFLOW_META',
						patch: {
							currentVersionId: version.id,
							currentVersionNumber: version.version_number,
							savingState: 'saved',
						},
					});
				},
				onError: () => dispatch({ type: 'SET_SAVE_STATE', savingState: 'error' }),
			},
		);
	};

	const handlePublish = () => {
		if (!state.workflow.apiId || !state.workflow.currentVersionId) return;
		publishVersion.mutate({
			id: state.workflow.apiId,
			version: state.workflow.currentVersionId,
		});
	};

	return (
		<header className='flex h-16 shrink-0 items-center gap-3 border-b border-zinc-200 bg-white/95 px-4 backdrop-blur-xl dark:border-white/10 dark:bg-zinc-950/92'>
			<IconButton
				title='Toggle node library'
				active={state.ui.leftPanelOpen}
				onClick={() => dispatch({ type: 'TOGGLE_LEFT_PANEL' })}>
				<PanelLeft size={16} />
			</IconButton>

			<div className='min-w-0 flex-1'>
				<div className='flex items-center gap-3'>
					<input
						value={state.workflow.name}
						aria-label='Workflow name'
						onChange={(event) =>
							dispatch({
								type: 'SET_WORKFLOW_META',
								patch: { name: event.target.value, savingState: 'dirty' },
							})
						}
						className='max-w-xl min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-1 py-1 text-[15px] font-semibold tracking-tight text-zinc-950 outline-none hover:border-zinc-200 focus:border-emerald-300/60 focus:bg-zinc-50 dark:text-white dark:hover:border-white/10 dark:focus:border-emerald-300/40 dark:focus:bg-white/[0.03]'
					/>
					<span className='rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700 dark:border-amber-300/20 dark:bg-amber-300/10 dark:text-amber-100'>
						Draft
					</span>
				</div>
				<div className='mt-0.5 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-500'>
					<Cloud size={13} />
					<span>{savingCopy[state.workflow.savingState]}</span>
					<span className='h-1 w-1 rounded-full bg-zinc-300 dark:bg-zinc-700' />
					<span>{state.nodes.length} nodes</span>
					<span className='h-1 w-1 rounded-full bg-zinc-300 dark:bg-zinc-700' />
					<span>Updated just now</span>
				</div>
			</div>

			<div className='flex items-center gap-2'>
				<IconButton
					title='Undo'
					onClick={() => dispatch({ type: 'UNDO' })}
					disabled={!state.history.past.length}>
					<RotateCcw size={16} />
				</IconButton>
				<IconButton
					title='Redo'
					onClick={() => dispatch({ type: 'REDO' })}
					disabled={!state.history.future.length}>
					<RotateCw size={16} />
				</IconButton>
				<IconButton title='Workflow settings'>
					<Settings2 size={16} />
				</IconButton>
				<IconButton
					title={isDarkTheme ? 'Switch to light mode' : 'Switch to dark mode'}
					onClick={() =>
						setDarkModeStatus(isDarkTheme ? DARK_MODE.LIGHT : DARK_MODE.DARK)
					}>
					{isDarkTheme ? <Sun size={16} /> : <Moon size={16} />}
				</IconButton>
				<PillButton onClick={() => dispatch({ type: 'TOGGLE_AI_PANEL' })}>
					<Bot size={15} />
					{state.ui.aiPanelOpen ? 'Hide AI' : 'Ask AI'}
				</PillButton>
				<PillButton onClick={handleSave} disabled={saveVersion.isPending}>
					<CheckCircle2 size={15} />
					{saveVersion.isPending ? 'Saving' : 'Save'}
				</PillButton>
				<PillButton
					variant='publish'
					onClick={handlePublish}
					disabled={!canPublish || publishVersion.isPending}>
					{publishVersion.isPending ? <UploadCloud size={15} /> : <Rocket size={15} />}
					{publishVersion.isPending ? 'Publishing' : 'Publish'}
				</PillButton>
				<motion.button
					whileTap={{ scale: 0.98 }}
					type='button'
					onClick={isRunning ? stopRun : runWorkflow}
					className={[
						'flex h-9 items-center gap-2 rounded-lg px-4 text-xs font-semibold shadow-lg transition',
						isRunning
							? 'bg-rose-500 text-white shadow-rose-950/30 hover:bg-rose-400'
							: 'border border-emerald-200 bg-emerald-50 text-emerald-700 shadow-emerald-100/50 hover:bg-emerald-100 dark:border-emerald-300/25 dark:bg-emerald-400/15 dark:text-emerald-100 dark:shadow-emerald-950/20 dark:hover:bg-emerald-400/20',
					].join(' ')}>
					{isRunning ? (
						<Square size={13} fill='currentColor' />
					) : (
						<Play size={14} fill='currentColor' />
					)}
					{isRunning ? 'Stop' : 'Run'}
				</motion.button>
			</div>
		</header>
	);
};

export default Topbar;
