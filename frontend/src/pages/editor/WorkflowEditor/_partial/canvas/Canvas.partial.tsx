import {
	Background,
	Controls,
	MiniMap,
	ReactFlow,
	useReactFlow,
	type Connection,
	type EdgeTypes,
	type IsValidConnection,
	type NodeChange,
	type NodeTypes,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { useCallback, useMemo, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Bot, Database, GitBranch, Globe2, MousePointer2, Timer, Webhook, Zap } from 'lucide-react';
import { useCanvasDrop } from '../../_hooks/useCanvasDrop.hook';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import BaseNode from './nodes/BaseNode.partial';
import InputNode from './nodes/InputNode.partial';
import OutputNode from './nodes/OutputNode.partial';
import StickyNote from './nodes/StickyNote.partial';
import CanvasEmptyState from './CanvasEmptyState.partial';
import CanvasStats from './CanvasStats.partial';
import ClickEdge from './ClickEdge.partial';
import useDarkMode from '@/hooks/useDarkMode';
import type { TCanvasNode } from '../../_types/canvas.type';
import { validateWorkflow } from '../../_helper/validation.helper';
import { getNodeDefinition, NODE_CATALOG_MAP } from '../../_helper/nodeCatalog.constants';
import { PORT_TYPE_COLOR } from '../../_helper/builder.constants';
import type { TPortType } from '../../_types/node.type';

const nodeTypes: NodeTypes = {
	base: BaseNode,
	input: InputNode,
	output: OutputNode,
	note: StickyNote,
};

const edgeTypes: EdgeTypes = {
	workflow: ClickEdge,
};

const quickAddNodes = [
	{ key: 'trigger.webhook', label: 'Webhook', icon: Webhook },
	{ key: 'ai.agent', label: 'AI Agent', icon: Bot },
	{ key: 'data.http', label: 'API', icon: Globe2 },
	{ key: 'data.database', label: 'Database', icon: Database },
	{ key: 'logic.condition', label: 'Condition', icon: GitBranch },
	{ key: 'utility.delay', label: 'Delay', icon: Timer },
];

const getPortType = (node: TCanvasNode | undefined, portId?: string | null): TPortType => {
	const def = node ? getNodeDefinition(node.data.defKey, node.data.definition) : undefined;
	const port = [...(def?.inputs ?? []), ...(def?.outputs ?? [])].find(
		(item) => item.id === portId,
	);
	return port?.type ?? 'any';
};

const getEdgeLabel = (
	nodes: TCanvasNode[],
	source: string,
	target: string,
	sourceHandle?: string | null,
	targetHandle?: string | null,
) => {
	const byId = new Map(nodes.map((node) => [node.id, node]));
	const sourceType = getPortType(byId.get(source), sourceHandle);
	const targetType = getPortType(byId.get(target), targetHandle);
	const type = sourceType === 'any' ? targetType : sourceType;
	return type === 'any' ? 'flow' : type;
};

const Canvas = () => {
	const { state, dispatch } = useWorkflowEditor();
	const { isDarkTheme } = useDarkMode();
	const reactFlow = useReactFlow<TCanvasNode>();
	const didDragNodeRef = useRef(false);
	const [isDraggingExistingNode, setIsDraggingExistingNode] = useState(false);
	const [contextMenu, setContextMenu] = useState<{
		x: number;
		y: number;
		flowPosition: { x: number; y: number };
	} | null>(null);

	const { isDraggingNode, onDragOver, onDragLeave, onDrop } = useCanvasDrop((event) =>
		reactFlow.screenToFlowPosition({ x: event.clientX, y: event.clientY }),
	);
	const validationIssues = useMemo(
		() => validateWorkflow(state.nodes, state.edges),
		[state.nodes, state.edges],
	);
	const issuesByNode = useMemo(() => {
		const result = new Map<string, typeof validationIssues>();
		validationIssues.forEach((issue) => {
			if (!issue.nodeId) return;
			result.set(issue.nodeId, [...(result.get(issue.nodeId) ?? []), issue]);
		});
		return result;
	}, [validationIssues]);

	// Create the nodes array that ReactFlow will use
	// During drag, we need to update positions locally, but sync back to store on drag end
	const storeNodes = useMemo(
		() =>
			state.nodes.map((node) => ({
				...node,
				selected: state.ui.selectedNodeId === node.id,
				draggable: !node.data.locked,
				data: {
					...node.data,
					validationIssues: issuesByNode.get(node.id) ?? [],
					isActiveRunNode: state.run.currentNodeId === node.id,
				},
			})),
		[issuesByNode, state.nodes, state.run.currentNodeId, state.ui.selectedNodeId],
	);

	const [dragPositions, setDragPositions] = useState<Map<string, { x: number; y: number }>>(
		() => new Map(),
	);

	const onNodesChange = useCallback(
		(changes: NodeChange<TCanvasNode>[]) => {
			const nextPositions = new Map<string, { x: number; y: number }>();
			changes.forEach((change) => {
				if (change.type === 'position' && 'position' in change && change.position) {
					nextPositions.set(change.id, change.position);
				}
				if (change.type === 'select' && change.selected) {
					dispatch({ type: 'SELECT_NODE', id: change.id, openInspector: false });
				}
			});
			if (nextPositions.size) {
				setDragPositions((previous) => new Map([...previous, ...nextPositions]));
			}
		},
		[dispatch],
	);

	// Create the nodes array that includes drag positions during active drag
	const nodes = useMemo(() => {
		return storeNodes.map((node) => {
			// Use drag position if available, otherwise use store position
			const dragPos = dragPositions.get(node.id);
			if (isDraggingExistingNode && dragPos) {
				return {
					...node,
					position: dragPos,
				};
			}
			return node;
		});
	}, [storeNodes, isDraggingExistingNode, dragPositions]);

	const edges = useMemo(
		() =>
			state.edges.map((edge) => {
				const sourceType = getPortType(
					state.nodes.find((node) => node.id === edge.source),
					edge.sourceHandle,
				);
				const targetType = getPortType(
					state.nodes.find((node) => node.id === edge.target),
					edge.targetHandle,
				);
				const typeMismatch =
					sourceType !== 'any' && targetType !== 'any' && sourceType !== targetType;
				const isActive =
					state.run.status === 'running' &&
					(edge.source === state.run.currentNodeId ||
						edge.target === state.run.currentNodeId);
				return {
					...edge,
					type: 'workflow' as const,
					animated: isActive,
					data: {
						...edge.data,
						label: getEdgeLabel(
							state.nodes,
							edge.source,
							edge.target,
							edge.sourceHandle,
							edge.targetHandle,
						),
						labelColor: PORT_TYPE_COLOR[sourceType === 'any' ? targetType : sourceType],
						isActive,
						issue: typeMismatch ? 'Port types do not match' : undefined,
					},
					style: {
						stroke: typeMismatch
							? 'rgb(244 63 94)'
							: isActive
								? 'rgb(16 185 129)'
								: 'rgb(139 92 246)',
						strokeWidth: isActive ? 3 : 2,
					},
				};
			}),
		[state.edges, state.nodes, state.run.currentNodeId, state.run.status],
	);

	const isValidConnection: IsValidConnection = useCallback(
		(connection) => {
			if (
				!connection.source ||
				!connection.target ||
				connection.source === connection.target
			) {
				return false;
			}
			const sourceType = getPortType(
				state.nodes.find((node) => node.id === connection.source),
				connection.sourceHandle,
			);
			const targetType = getPortType(
				state.nodes.find((node) => node.id === connection.target),
				connection.targetHandle,
			);
			return sourceType === 'any' || targetType === 'any' || sourceType === targetType;
		},
		[state.nodes],
	);

	const onConnect = useCallback(
		(connection: Connection) => {
			if (!connection.source || !connection.target) return;
			dispatch({
				type: 'ADD_EDGE',
				source: connection.source,
				target: connection.target,
				sourceHandle: connection.sourceHandle ?? undefined,
				targetHandle: connection.targetHandle ?? undefined,
			});
		},
		[dispatch],
	);

	// Clear drag positions when drag ends and sync to store
	const handleDragStop = useCallback(
		(_event: unknown, node: TCanvasNode) => {
			setIsDraggingExistingNode(false);
			didDragNodeRef.current = false;

			// Get the final position from drag positions or node
			const finalPos = dragPositions.get(node.id) ?? node.position;
			setDragPositions((previous) => {
				const next = new Map(previous);
				next.delete(node.id);
				return next;
			});

			// Dispatch to store
			dispatch({ type: 'MOVE_NODE', id: node.id, position: finalPos });
		},
		[dispatch, dragPositions],
	);

	return (
		<section
			data-canvas='true'
			className='relative min-h-0 flex-1 overflow-hidden bg-zinc-50 dark:bg-[#090a0f]'
			onContextMenu={(event) => event.preventDefault()}
			onDragOver={onDragOver}
			onDragLeave={onDragLeave}
			onDrop={onDrop}>
			<ReactFlow
				fitView
				snapToGrid
				snapGrid={[18, 18]}
				selectionOnDrag
				multiSelectionKeyCode={['Meta', 'Shift']}
				reconnectRadius={18}
				nodes={nodes}
				edges={edges}
				nodeTypes={nodeTypes}
				edgeTypes={edgeTypes}
				onNodesChange={onNodesChange}
				onConnect={onConnect}
				isValidConnection={isValidConnection}
				onNodeDragStart={(_, node) => {
					didDragNodeRef.current = true;
					setIsDraggingExistingNode(true);
					dispatch({ type: 'SELECT_NODE', id: node.id, openInspector: false });
				}}
				onNodeDragStop={handleDragStop}
				onPaneClick={() => {
					setContextMenu(null);
					dispatch({ type: 'SELECT_NODE', id: null });
				}}
				onPaneContextMenu={(event) => {
					event.preventDefault();
					setContextMenu({
						x: event.clientX,
						y: event.clientY,
						flowPosition: reactFlow.screenToFlowPosition({
							x: event.clientX,
							y: event.clientY,
						}),
					});
				}}
				onNodeClick={(_, node) => {
					setContextMenu(null);
					if (didDragNodeRef.current) {
						didDragNodeRef.current = false;
						return;
					}
					dispatch({ type: 'SELECT_NODE', id: node.id });
				}}
				onEdgesDelete={(deletedEdges) =>
					deletedEdges.forEach((edge) => dispatch({ type: 'REMOVE_EDGE', id: edge.id }))
				}
				deleteKeyCode={null}
				defaultViewport={{ x: 0, y: 0, zoom: 1 }}
				minZoom={0.2}
				maxZoom={1.5}
				colorMode={isDarkTheme ? 'dark' : 'light'}
				className='workflow-react-flow'>
				<Background
					color={isDarkTheme ? 'rgba(255,255,255,.18)' : 'rgba(24,24,27,.14)'}
					gap={30}
					size={1}
				/>
				<div className='pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_0%,rgba(16,185,129,0.08),transparent_34%),linear-gradient(180deg,rgba(255,255,255,0.55),transparent_24%)] dark:bg-[radial-gradient(circle_at_50%_0%,rgba(16,185,129,0.08),transparent_34%),linear-gradient(180deg,rgba(255,255,255,0.025),transparent_24%)]' />
				<Controls
					position='bottom-right'
					className='overflow-hidden rounded-xl border border-zinc-200 bg-white/90 text-zinc-950 shadow-2xl shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/80 dark:text-white dark:shadow-black/30'
				/>
				{state.ui.miniMapOpen && (
					<MiniMap
						nodeStrokeWidth={3}
						position='bottom-left'
						pannable
						zoomable
						className='overflow-hidden rounded-xl border border-zinc-200 bg-white/90 shadow-2xl shadow-zinc-200/60 backdrop-blur dark:border-white/10 dark:bg-zinc-950/90 dark:shadow-black/30'
					/>
				)}
			</ReactFlow>
			<AnimatePresence>
				{contextMenu && (
					<motion.div
						initial={{ opacity: 0, scale: 0.96, y: 4 }}
						animate={{ opacity: 1, scale: 1, y: 0 }}
						exit={{ opacity: 0, scale: 0.96, y: 4 }}
						transition={{ duration: 0.12 }}
						style={{ left: contextMenu.x, top: contextMenu.y }}
						className='absolute z-30 w-64 overflow-hidden rounded-xl border border-white/10 bg-zinc-950/95 p-2 text-zinc-100 shadow-2xl shadow-black/40 backdrop-blur-xl'>
						<div className='mb-1 flex items-center gap-2 px-2 py-1.5 text-xs font-semibold text-zinc-500'>
							<MousePointer2 size={13} />
							Add node here
						</div>
						{quickAddNodes.map((node) => {
							const Icon = node.icon;
							return (
								<button
									key={node.key}
									type='button'
									onClick={() => {
										dispatch({
											type: 'ADD_NODE',
											defKey: node.key,
											definition: NODE_CATALOG_MAP[node.key],
											position: contextMenu.flowPosition,
										});
										setContextMenu(null);
									}}
									className='flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm text-zinc-300 transition hover:bg-white/[0.07] hover:text-white'>
									<span className='flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 bg-white/[0.04]'>
										<Icon size={15} />
									</span>
									{node.label}
								</button>
							);
						})}
						<button
							type='button'
							onClick={() => {
								dispatch({ type: 'SET_COMMAND_PALETTE', open: true });
								setContextMenu(null);
							}}
							className='mt-1 flex w-full items-center gap-3 rounded-lg border border-emerald-300/20 bg-emerald-400/10 px-2.5 py-2 text-left text-sm font-medium text-emerald-100 transition hover:bg-emerald-400/15'>
							<span className='flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-300/15'>
								<Zap size={15} />
							</span>
							Open command palette
						</button>
					</motion.div>
				)}
			</AnimatePresence>
			{state.nodes.length === 0 ? (
				<CanvasEmptyState />
			) : (
				<CanvasStats nodes={state.nodes.length} edges={state.edges.length} />
			)}
			{isDraggingNode && (
				<div className='pointer-events-none absolute inset-4 rounded-2xl border-2 border-dashed border-emerald-400/70 bg-emerald-400/10' />
			)}
		</section>
	);
};

export default Canvas;
