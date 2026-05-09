import { useState } from 'react';
import { getNodeDefinition } from '../../_helper/nodeCatalog.constants';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import NodeDataPreview from './NodeDataPreview.partial';
import NodeDocs from './NodeDocs.partial';
import NodeInputs from './NodeInputs.partial';
import NodeOutputs from './NodeOutputs.partial';
import NodeSettings from './NodeSettings.partial';
import type { TNodeRunStatus } from '../../_types/node.type';

type TabKey = 'settings' | 'ports' | 'preview';

const getStatusStyles = (status: TNodeRunStatus) => {
	switch (status) {
		case 'running':
			return 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300';
		case 'success':
			return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300';
		case 'error':
			return 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300';
		case 'skipped':
			return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300';
		default:
			return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300';
	}
};

const Inspector = () => {
	const { state, dispatch } = useWorkflowEditor();
	const selected = state.nodes.find((node) => node.id === state.ui.selectedNodeId);
	const def = selected ? getNodeDefinition(selected.data.defKey, selected.data.definition) : null;
	const isOpen = state.ui.rightPanelOpen && Boolean(selected && def);
	const [activeTab, setActiveTab] = useState<TabKey>('settings');

	if (!isOpen || !selected || !def) return null;

	const tabs: { key: TabKey; label: string; count?: number }[] = [
		{ key: 'settings', label: 'Settings' },
		{
			key: 'ports',
			label: 'Ports',
			count: def.inputs.length + def.outputs.length,
		},
		{ key: 'preview', label: 'Preview' },
	];

	return (
		<aside
			aria-labelledby='node-inspector-title'
			className='flex h-full min-h-0 w-full overflow-hidden border-l border-zinc-200 bg-white text-zinc-950 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100'>
			<div className='flex min-h-0 w-full flex-col'>
				{/* Header with node info */}
				<div className='border-b border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/60'>
					<div className='flex items-start gap-3'>
						<div
							className='flex h-11 w-11 items-center justify-center rounded-lg border text-base font-black'
							style={{
								borderColor:
									def.color === 'emerald' ? 'rgb(52 211 153)' : undefined,
							}}>
							{def.icon}
						</div>
						<div className='min-w-0 flex-1'>
							<div className='text-xs font-black tracking-widest text-zinc-500 uppercase dark:text-zinc-400'>
								{def.category}
							</div>
							<div className='mt-0.5 truncate text-lg font-black text-zinc-950 dark:text-white'>
								{selected.data.label}
							</div>
							<p className='mt-1.5 line-clamp-2 text-sm leading-5 text-zinc-600 dark:text-zinc-300'>
								{def.description}
							</p>
						</div>
						{selected.data.status && selected.data.status !== 'idle' && (
							<div className='flex items-center gap-2'>
								<span
									className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${getStatusStyles(selected.data.status)}`}>
									{selected.data.status}
								</span>
								{selected.data.durationMs && (
									<span className='text-xs text-zinc-500'>
										{selected.data.durationMs}ms
									</span>
								)}
							</div>
						)}
						{selected.data.error && (
							<div className='mt-2 rounded-md border border-rose-200 bg-rose-50 p-2 dark:border-rose-800/50 dark:bg-rose-950/30'>
								<p className='text-xs text-rose-600 dark:text-rose-300'>
									{selected.data.error}
								</p>
							</div>
						)}
					</div>

					{/* Stats */}
					<div className='mt-4 grid grid-cols-2 gap-2 text-sm'>
						<div className='rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950'>
							<div className='text-xs text-zinc-500'>Inputs</div>
							<div className='mt-1 text-lg font-black'>{def.inputs.length}</div>
						</div>
						<div className='rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950'>
							<div className='text-xs text-zinc-500'>Outputs</div>
							<div className='mt-1 text-lg font-black'>{def.outputs.length}</div>
						</div>
					</div>

					{/* Actions */}
					<div className='mt-4 flex gap-2'>
						<button
							type='button'
							onClick={() => dispatch({ type: 'DUPLICATE_SELECTED' })}
							disabled={!state.ui.selectedNodeId}
							className='flex-1 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-bold text-zinc-700 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-900'>
							Duplicate
						</button>
						<button
							type='button'
							onClick={() => dispatch({ type: 'DELETE_SELECTED' })}
							disabled={!state.ui.selectedNodeId}
							className='flex-1 rounded-lg bg-rose-500 px-3 py-2 text-xs font-bold text-white hover:bg-rose-600 disabled:cursor-not-allowed disabled:opacity-50'>
							Delete
						</button>
						<button
							type='button'
							onClick={() => dispatch({ type: 'SELECT_NODE', id: null })}
							className='flex-1 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-bold text-zinc-700 hover:bg-zinc-100 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-900'>
							Deselect
						</button>
					</div>
				</div>

				{/* Tab Navigation */}
				<nav
					className='flex shrink-0 items-center justify-between border-b border-zinc-200 bg-zinc-50/50 px-1 dark:border-zinc-800 dark:bg-zinc-900/30'
					role='tablist'>
					<div className='flex'>
						{tabs.map((tab) => (
							<button
								key={tab.key}
								type='button'
								role='tab'
								aria-selected={activeTab === tab.key}
								aria-controls={`${tab.key}-panel`}
								onClick={() => setActiveTab(tab.key)}
								className={`relative px-4 py-2.5 text-sm font-medium transition-colors ${
									activeTab === tab.key
										? 'text-zinc-950 dark:text-white'
										: 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200'
								} `}>
								{tab.label}
								{tab.count !== undefined && tab.count > 0 && (
									<span className='ml-1.5 rounded-full bg-zinc-200 px-1.5 py-0.5 text-xs dark:bg-zinc-700'>
										{tab.count}
									</span>
								)}
								{activeTab === tab.key && (
									<span className='absolute right-0 bottom-0 left-0 mx-auto h-0.5 w-12 rounded-full bg-emerald-500' />
								)}
							</button>
						))}
					</div>

					{/* Close button */}
					<button
						type='button'
						onClick={() => dispatch({ type: 'TOGGLE_RIGHT_PANEL' })}
						className='rounded-lg border border-zinc-200 px-2.5 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-white'
						aria-label='Close inspector'>
						✕
					</button>
				</nav>

				{/* Tab Panels */}
				<div
					id='settings-panel'
					role='tabpanel'
					aria-labelledby='settings-tab'
					className={`min-h-0 flex-1 overflow-y-auto p-4 ${activeTab === 'settings' ? '' : 'hidden'}`}>
					<NodeSettings nodeId={selected.id} />
				</div>

				<div
					id='ports-panel'
					role='tabpanel'
					aria-labelledby='ports-tab'
					className={`min-h-0 flex-1 overflow-y-auto p-4 ${activeTab === 'ports' ? '' : 'hidden'}`}>
					<div className='space-y-5'>
						<NodeInputs def={def} />
						<NodeOutputs def={def} />
					</div>
				</div>

				<div
					id='preview-panel'
					role='tabpanel'
					aria-labelledby='preview-tab'
					className={`min-h-0 flex-1 overflow-y-auto p-4 ${activeTab === 'preview' ? '' : 'hidden'}`}>
					<NodeDataPreview node={selected} />
					<NodeDocs def={def} />
				</div>
			</div>
		</aside>
	);
};

export default Inspector;
