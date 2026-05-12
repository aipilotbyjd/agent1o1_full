import { useState } from 'react';
import { Bot, Copy, Trash2, X } from 'lucide-react';
import { getNodeDefinition } from '../../_helper/nodeCatalog.constants';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import NodeDataPreview from './NodeDataPreview.partial';
import NodeDocs from './NodeDocs.partial';
import NodeInputs from './NodeInputs.partial';
import NodeOutputs from './NodeOutputs.partial';
import NodeSettings from './NodeSettings.partial';
import type { TNodeRunStatus } from '../../_types/node.type';

type TabKey = 'settings' | 'inputs' | 'outputs' | 'logs';

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
		{ key: 'inputs', label: 'Inputs', count: def.inputs.length },
		{ key: 'outputs', label: 'Outputs', count: def.outputs.length },
		{ key: 'logs', label: 'Logs', count: state.run.logs.length },
	];

	return (
		<aside
			aria-labelledby='node-inspector-title'
			className='flex h-full min-h-0 w-full overflow-hidden border-l border-white/10 bg-zinc-950 text-zinc-100'>
			<div className='flex min-h-0 w-full flex-col'>
				<div className='border-b border-white/10 bg-white/[0.025] p-4'>
					<div className='flex items-start gap-3'>
						<div
							className='flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/[0.04] text-base font-black text-emerald-100'
							style={{
								borderColor:
									def.color === 'emerald' ? 'rgb(52 211 153)' : undefined,
							}}>
							{def.icon}
						</div>
						<div className='min-w-0 flex-1'>
							<div className='text-xs font-semibold tracking-[0.16em] text-zinc-600 uppercase'>
								{def.category}
							</div>
							<div className='mt-0.5 truncate text-lg font-semibold tracking-tight text-white'>
								{selected.data.label}
							</div>
							<p className='mt-1.5 line-clamp-2 text-sm leading-5 text-zinc-500'>
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

					<div className='mt-4 grid grid-cols-2 gap-2 text-sm'>
						<div className='rounded-xl border border-white/10 bg-white/[0.03] p-3'>
							<div className='text-xs text-zinc-500'>Inputs</div>
							<div className='mt-1 text-lg font-semibold'>{def.inputs.length}</div>
						</div>
						<div className='rounded-xl border border-white/10 bg-white/[0.03] p-3'>
							<div className='text-xs text-zinc-500'>Outputs</div>
							<div className='mt-1 text-lg font-semibold'>{def.outputs.length}</div>
						</div>
					</div>

					<div className='mt-4 flex gap-2'>
						<button
							type='button'
							onClick={() => dispatch({ type: 'DUPLICATE_SELECTED' })}
							disabled={!state.ui.selectedNodeId}
							className='flex flex-1 items-center justify-center gap-2 rounded-lg border border-white/10 bg-white/[0.03] px-3 py-2 text-xs font-semibold text-zinc-300 hover:bg-white/[0.07] disabled:cursor-not-allowed disabled:opacity-50'>
							<Copy size={13} />
							Clone
						</button>
						<button
							type='button'
							onClick={() => dispatch({ type: 'DELETE_SELECTED' })}
							disabled={!state.ui.selectedNodeId}
							className='flex flex-1 items-center justify-center gap-2 rounded-lg bg-rose-500/90 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50'>
							<Trash2 size={13} />
							Delete
						</button>
						<button
							type='button'
							onClick={() => dispatch({ type: 'SELECT_NODE', id: null })}
							className='flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-white/[0.03] text-zinc-400 hover:bg-white/[0.07] hover:text-white'>
							<X size={14} />
						</button>
					</div>
				</div>

				<nav
					className='flex shrink-0 items-center justify-between border-b border-white/10 bg-white/[0.018] px-1'
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
										? 'text-white'
										: 'text-zinc-500 hover:text-zinc-200'
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
						className='rounded-lg border border-white/10 px-2.5 py-1.5 text-xs font-medium text-zinc-400 hover:bg-white/[0.06] hover:text-white'
						aria-label='Close inspector'>
						<X size={13} />
					</button>
				</nav>

				<div
					id='settings-panel'
					role='tabpanel'
					aria-labelledby='settings-tab'
					className={`min-h-0 flex-1 overflow-y-auto p-4 ${activeTab === 'settings' ? '' : 'hidden'}`}>
					<NodeSettings nodeId={selected.id} />
				</div>

				<div
					id='inputs-panel'
					role='tabpanel'
					aria-labelledby='inputs-tab'
					className={`min-h-0 flex-1 overflow-y-auto p-4 ${activeTab === 'inputs' ? '' : 'hidden'}`}>
					<NodeInputs def={def} />
				</div>

				<div
					id='outputs-panel'
					role='tabpanel'
					aria-labelledby='outputs-tab'
					className={`min-h-0 flex-1 overflow-y-auto p-4 ${activeTab === 'outputs' ? '' : 'hidden'}`}>
					<NodeOutputs def={def} />
					<NodeDataPreview node={selected} />
					<NodeDocs def={def} />
				</div>

				<div
					id='logs-panel'
					role='tabpanel'
					aria-labelledby='logs-tab'
					className={`min-h-0 flex-1 overflow-y-auto p-4 ${activeTab === 'logs' ? '' : 'hidden'}`}>
					<div className='rounded-xl border border-white/10 bg-black/20 p-3'>
						<div className='mb-3 flex items-center gap-2 text-sm font-semibold text-white'>
							<Bot size={15} />
							Execution logs
						</div>
						{state.run.logs.length ? (
							<div className='space-y-2'>
								{state.run.logs.map((log) => (
									<div
										key={log.id}
										className='rounded-lg border border-white/10 bg-white/[0.03] px-3 py-2 text-xs text-zinc-300'>
										<div className='font-mono text-zinc-500'>
											{new Date(log.at).toLocaleTimeString()}
										</div>
										<div className='mt-1'>{log.message}</div>
									</div>
								))}
							</div>
						) : (
							<div className='rounded-lg border border-dashed border-white/10 p-4 text-sm text-zinc-500'>
								Run this workflow to stream node logs here.
							</div>
						)}
					</div>
				</div>
			</div>
		</aside>
	);
};

export default Inspector;
