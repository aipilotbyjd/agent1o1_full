import { useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Resizable } from 're-resizable';
import AiBuilderPanel from '../_partial/ai/AiBuilderPanel.partial';
import Canvas from '../_partial/canvas/Canvas.partial';
import CommandPalette from '../_partial/dialogs/CommandPalette.partial';
import ImportExportDialog from '../_partial/dialogs/ImportExportDialog.partial';
import QuickAddNodeDialog from '../_partial/dialogs/QuickAddNodeDialog.partial';
import Inspector from '../_partial/inspector/Inspector.partial';
import NodeLibrary from '../_partial/library/NodeLibrary.partial';
import RunPanel from '../_partial/run/RunPanel.partial';
import ActionBar from '../_partial/shell/ActionBar.partial';
import AgentBuilderSurface from '../_partial/shell/AgentBuilderSurface.partial';
import StatusBar from '../_partial/shell/StatusBar.partial';
import Topbar from '../_partial/shell/Topbar.partial';
import WorkflowLaunchSurface from '../_partial/shell/WorkflowLaunchSurface.partial';
import WorkspaceSidebar from '../_partial/shell/WorkspaceSidebar.partial';
import { useAutosave } from '../_hooks/useAutosave.hook';
import { useEditorHotkeys } from '../_hooks/useEditorHotkeys.hook';
import { useWorkflowApiLoader } from '../_hooks/useWorkflowApiLoader.hook';
import { useWorkflowRouteParams } from '../_hooks/useWorkflowRouteParams.hook';
import { useWorkflowEditor } from '../_context/WorkflowEditorProvider.context';
import { useWorkflowShellStore } from '@/store/workflowShell.store';

const BuildPage = () => {
	const { state } = useWorkflowEditor();
	const activeWorkspaceView = useWorkflowShellStore((store) => store.activeWorkspaceView);
	const mobileSidebarOpen = useWorkflowShellStore((store) => store.mobileSidebarOpen);
	const closeMobileSidebar = useWorkflowShellStore((store) => store.closeMobileSidebar);
	const { workspaceId, workflowId } = useWorkflowRouteParams();
	const apiState = useWorkflowApiLoader(workspaceId, workflowId);
	const [leftPanelWidth, setLeftPanelWidth] = useState(320);
	const [aiPanelWidth, setAiPanelWidth] = useState(400);
	const [rightPanelWidth, setRightPanelWidth] = useState(420);
	const [runPanelHeight, setRunPanelHeight] = useState(300);

	useAutosave();
	useEditorHotkeys();

	if (apiState.isLoading && workspaceId && workflowId) {
		return (
			<div className='flex h-full items-center justify-center bg-white text-sm font-bold text-zinc-500 dark:bg-zinc-950 dark:text-zinc-400'>
				Loading workflow...
			</div>
		);
	}

	return (
		<div className='flex h-full min-h-0 bg-zinc-50 dark:bg-[#07080b]'>
			{apiState.isError && (
				<div className='absolute top-0 right-0 left-0 z-50 flex items-center justify-center gap-2 bg-rose-500 px-4 py-1 text-xs font-bold text-white'>
					API unavailable — running in local mode.
				</div>
			)}
			<div className='hidden shrink-0 lg:block'>
				<WorkspaceSidebar />
			</div>
			<AnimatePresence>
				{mobileSidebarOpen && (
					<motion.div
						initial={{ opacity: 0 }}
						animate={{ opacity: 1 }}
						exit={{ opacity: 0 }}
						className='fixed inset-0 z-[80] lg:hidden'>
						<button
							type='button'
							aria-label='Close sidebar'
							onClick={closeMobileSidebar}
							className='absolute inset-0 bg-zinc-950/35 backdrop-blur-sm'
						/>
						<motion.div
							initial={{ x: -300 }}
							animate={{ x: 0 }}
							exit={{ x: -300 }}
							transition={{ duration: 0.2, ease: [0.22, 1, 0.36, 1] }}
							className='relative h-full w-[286px] max-w-[86vw]'>
							<WorkspaceSidebar />
						</motion.div>
					</motion.div>
				)}
			</AnimatePresence>
			{activeWorkspaceView === 'workflows' ? (
				<WorkflowLaunchSurface />
			) : activeWorkspaceView === 'agents' ? (
				<AgentBuilderSurface />
			) : (
				<>
					<AnimatePresence initial={false}>
						{state.ui.aiPanelOpen && (
							<motion.div
								initial={{ width: 0, opacity: 0 }}
								animate={{ width: aiPanelWidth, opacity: 1 }}
								exit={{ width: 0, opacity: 0 }}
								transition={{ duration: 0.2 }}
								className='min-h-0 shrink-0 overflow-hidden'>
								<Resizable
									size={{ width: aiPanelWidth, height: '100%' }}
									minWidth={360}
									maxWidth='45vw'
									enable={{ right: true }}
									onResizeStop={(_, __, ref) => setAiPanelWidth(ref.offsetWidth)}
									className='min-h-0 shrink-0'>
									<AiBuilderPanel />
								</Resizable>
							</motion.div>
						)}
					</AnimatePresence>
					<div className='flex min-w-0 flex-1 flex-col overflow-hidden rounded-l-2xl border-l border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-950'>
						<Topbar />
						<div className='flex min-h-0 flex-1'>
							<AnimatePresence initial={false}>
								{state.ui.leftPanelOpen && (
									<motion.div
										initial={{ width: 0, opacity: 0 }}
										animate={{ width: leftPanelWidth, opacity: 1 }}
										exit={{ width: 0, opacity: 0 }}
										transition={{ duration: 0.2 }}
										className='min-h-0 shrink-0 overflow-hidden'>
										<Resizable
											size={{ width: leftPanelWidth, height: '100%' }}
											minWidth={260}
											maxWidth={460}
											enable={{ right: true }}
											onResizeStop={(_, __, ref) =>
												setLeftPanelWidth(ref.offsetWidth)
											}
											className='min-h-0 shrink-0'>
											<NodeLibrary />
										</Resizable>
									</motion.div>
								)}
							</AnimatePresence>
							<div className='relative flex min-w-0 flex-1 flex-col'>
								<Canvas />
								<ActionBar />
							</div>
							<AnimatePresence initial={false}>
								{state.ui.rightPanelOpen && state.ui.selectedNodeId && (
									<motion.div
										initial={{ width: 0, opacity: 0 }}
										animate={{ width: rightPanelWidth, opacity: 1 }}
										exit={{ width: 0, opacity: 0 }}
										transition={{ duration: 0.2 }}
										className='min-h-0 shrink-0 overflow-hidden'>
										<Resizable
											size={{ width: rightPanelWidth, height: '100%' }}
											minWidth={340}
											maxWidth={560}
											enable={{ left: true }}
											onResizeStop={(_, __, ref) =>
												setRightPanelWidth(ref.offsetWidth)
											}
											className='min-h-0 shrink-0'>
											<Inspector />
										</Resizable>
									</motion.div>
								)}
							</AnimatePresence>
						</div>
						{state.ui.runPanelOpen && (
							<Resizable
								size={{ width: '100%', height: runPanelHeight }}
								minHeight={180}
								maxHeight='58vh'
								enable={{ top: true }}
								onResizeStop={(_, __, ref) => setRunPanelHeight(ref.offsetHeight)}
								className='shrink-0'>
								<RunPanel />
							</Resizable>
						)}
						<StatusBar />
					</div>
				</>
			)}
			<CommandPalette />
			<QuickAddNodeDialog />
			<ImportExportDialog />
		</div>
	);
};

export default BuildPage;
