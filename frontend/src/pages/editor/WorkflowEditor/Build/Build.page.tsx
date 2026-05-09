import { useState } from 'react';
import { Resizable } from 're-resizable';
import AiBuilderPanel from '../_partial/ai/AiBuilderPanel.partial';
import Canvas from '../_partial/canvas/Canvas.partial';
import CommandPalette from '../_partial/dialogs/CommandPalette.partial';
import ImportExportDialog from '../_partial/dialogs/ImportExportDialog.partial';
import Inspector from '../_partial/inspector/Inspector.partial';
import NodeLibrary from '../_partial/library/NodeLibrary.partial';
import RunPanel from '../_partial/run/RunPanel.partial';
import ActionBar from '../_partial/shell/ActionBar.partial';
import StatusBar from '../_partial/shell/StatusBar.partial';
import Topbar from '../_partial/shell/Topbar.partial';
import { useAutosave } from '../_hooks/useAutosave.hook';
import { useEditorHotkeys } from '../_hooks/useEditorHotkeys.hook';
import { useWorkflowApiLoader } from '../_hooks/useWorkflowApiLoader.hook';
import { useWorkflowRouteParams } from '../_hooks/useWorkflowRouteParams.hook';
import { useWorkflowEditor } from '../_context/WorkflowEditorProvider.context';

const BuildPage = () => {
	const { state } = useWorkflowEditor();
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
		<div className='flex h-full min-h-0'>
			{apiState.isError && (
				<div className='absolute top-0 left-0 right-0 z-50 flex items-center justify-center gap-2 bg-rose-500 px-4 py-1 text-xs font-bold text-white'>
					API unavailable — running in local mode.
				</div>
			)}
			{state.ui.aiPanelOpen && (
				<Resizable
					size={{ width: aiPanelWidth, height: '100%' }}
					minWidth={360}
					maxWidth='45vw'
					enable={{ right: true }}
					onResizeStop={(_, __, ref) => setAiPanelWidth(ref.offsetWidth)}
					className='min-h-0 shrink-0'>
					<AiBuilderPanel />
				</Resizable>
			)}
			<div className='flex min-w-0 flex-1 flex-col'>
				<Topbar />
				<div className='flex min-h-0 flex-1'>
					{state.ui.leftPanelOpen && (
						<Resizable
							size={{ width: leftPanelWidth, height: '100%' }}
							minWidth={260}
							maxWidth={460}
							enable={{ right: true }}
							onResizeStop={(_, __, ref) => setLeftPanelWidth(ref.offsetWidth)}
							className='min-h-0 shrink-0'>
							<NodeLibrary />
						</Resizable>
					)}
					<div className='relative flex min-w-0 flex-1 flex-col'>
						<Canvas />
						<ActionBar />
					</div>
					{state.ui.rightPanelOpen && state.ui.selectedNodeId && (
						<Resizable
							size={{ width: rightPanelWidth, height: '100%' }}
							minWidth={340}
							maxWidth={560}
							enable={{ left: true }}
							onResizeStop={(_, __, ref) => setRightPanelWidth(ref.offsetWidth)}
							className='min-h-0 shrink-0'>
							<Inspector />
						</Resizable>
					)}
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
			<CommandPalette />
			<ImportExportDialog />
		</div>
	);
};

export default BuildPage;
