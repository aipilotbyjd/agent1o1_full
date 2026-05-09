import { Link } from 'react-router';
import { useCreateWorkflowVersion, usePublishWorkflowVersion } from '@/api/modules/workflows';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import { buildVersionPayload } from '../../_helper/workflowApiTransform.helper';
import { useRunWorkflow } from '../../_hooks/useRunWorkflow.hook';
import DarkModeSwitcherPart from '@/parts/DarkModeSwitcher.part';
import Icon from '@/components/icon/Icon';

const TopbarIconButton = ({
	title,
	icon,
	onClick,
	disabled,
	active,
}: {
	title: string;
	icon: string;
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
			'flex h-9 w-9 items-center justify-center rounded-lg border text-lg transition',
			active
				? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-300'
				: 'border-zinc-200 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-white',
			disabled ? 'cursor-not-allowed opacity-30' : '',
		].join(' ')}>
		<Icon icon={icon} />
	</button>
);

const Topbar = () => {
	const { state, dispatch } = useWorkflowEditor();
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
		<header className='flex h-14 shrink-0 items-center gap-2 border-b border-zinc-200 bg-white px-3 dark:border-zinc-800 dark:bg-zinc-950'>
			<TopbarIconButton
				title='Toggle node library'
				icon='SidebarLeft'
				active={state.ui.leftPanelOpen}
				onClick={() => dispatch({ type: 'TOGGLE_LEFT_PANEL' })}
			/>
			<Link
				to='/'
				title='Home'
				aria-label='Home'
				className='flex h-9 w-9 items-center justify-center rounded-lg border border-zinc-200 text-lg text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-white'>
				<Icon icon='Home01' />
			</Link>
			<input
				value={state.workflow.name}
				aria-label='Workflow name'
				onChange={(event) =>
					dispatch({
						type: 'SET_WORKFLOW_META',
						patch: { name: event.target.value, savingState: 'dirty' },
					})
				}
				className='max-w-md min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-3 py-2 text-sm font-black text-zinc-900 outline-none hover:border-zinc-200 focus:border-emerald-400 dark:text-white dark:hover:border-zinc-800'
			/>
			<div className='ml-auto flex items-center gap-4'>
				<div className='flex items-center gap-2'>
					<TopbarIconButton
						title='Undo'
						icon='ArrowLeft02'
						onClick={() => dispatch({ type: 'UNDO' })}
						disabled={!state.history.past.length}
					/>
					<TopbarIconButton
						title='Redo'
						icon='ArrowRight02'
						onClick={() => dispatch({ type: 'REDO' })}
						disabled={!state.history.future.length}
					/>
					<TopbarIconButton
						title='Auto-layout'
						icon='FitToScreen'
						onClick={() => dispatch({ type: 'AUTO_LAYOUT' })}
					/>
					<button
						type='button'
						onClick={handleSave}
						disabled={saveVersion.isPending}
						className='flex h-9 items-center gap-2 rounded-lg border border-zinc-200 px-3 text-xs font-black text-zinc-700 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900'>
						<Icon icon='FloppyDisk' className='text-base' />
						{saveVersion.isPending ? 'Saving' : 'Save'}
					</button>
					<button
						type='button'
						onClick={handlePublish}
						disabled={!canPublish || publishVersion.isPending}
						className='flex h-9 items-center gap-2 rounded-lg border border-zinc-200 px-3 text-xs font-black text-zinc-700 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-900'>
						<Icon icon='Upload01' className='text-base' />
						{publishVersion.isPending ? 'Publishing' : 'Publish'}
					</button>
					<button
						type='button'
						title={state.ui.aiPanelOpen ? 'Collapse AI builder' : 'Ask AI for help'}
						aria-label={
							state.ui.aiPanelOpen ? 'Collapse AI builder' : 'Ask AI for help'
						}
						onClick={() => dispatch({ type: 'TOGGLE_AI_PANEL' })}
						className={[
							'flex h-9 items-center gap-2 rounded-lg border px-3 text-xs font-black transition',
							state.ui.aiPanelOpen
								? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-300'
								: 'border-zinc-200 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-white',
						].join(' ')}>
						<Icon icon='AiMagic' className='text-base' />
						{state.ui.aiPanelOpen ? 'Collapse AI' : 'Ask AI'}
					</button>
					<button
						type='button'
						onClick={isRunning ? stopRun : runWorkflow}
						className={`flex h-9 items-center gap-2 rounded-lg px-4 text-xs font-black text-white ${isRunning ? 'bg-rose-500 hover:bg-rose-600' : 'bg-emerald-500 hover:bg-emerald-600'}`}>
						<Icon icon={isRunning ? 'Stop' : 'Play'} className='text-base' />
						{isRunning ? 'Stop' : 'Run'}
					</button>
				</div>

				<div className='h-8 w-px bg-zinc-200 dark:bg-zinc-800' />

				<div className='w-40'>
					<DarkModeSwitcherPart />
				</div>
			</div>
		</header>
	);
};

export default Topbar;
