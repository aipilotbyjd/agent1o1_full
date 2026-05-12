import { Handle, Position, type NodeProps } from '@xyflow/react';
import {
	Bot,
	Braces,
	CheckCircle2,
	Clock3,
	Database,
	GitBranch,
	Globe2,
	Loader2,
	MessageSquare,
	TriangleAlert,
	Webhook,
	Zap,
} from 'lucide-react';
import { motion } from 'framer-motion';
import { getNodeDefinition } from '../../../_helper/nodeCatalog.constants';
import { HUE_TO_CLASSES, PORT_TYPE_COLOR } from '../../../_helper/builder.constants';
import type { TCanvasNode } from '../../../_types/canvas.type';
import type { TNodePort } from '../../../_types/node.type';
import type { TValidationIssue } from '../../../_helper/validation.helper';

const getPortTop = (index: number, total: number) => `${((index + 1) * 100) / (total + 1)}%`;

const iconMap = {
	'trigger.webhook': Webhook,
	'ai.agent': Bot,
	'ai.chat': MessageSquare,
	'ai.extract': Braces,
	'data.http': Globe2,
	'data.database': Database,
	'logic.condition': GitBranch,
	'logic.if': GitBranch,
	'utility.delay': Clock3,
	'output.display': Zap,
};

const PortHandles = ({
	ports,
	type,
	position,
}: {
	ports: TNodePort[];
	type: 'source' | 'target';
	position: Position;
}) => (
	<>
		{ports.map((port, index) => (
			<Handle
				key={port.id}
				id={port.id}
				type={type}
				position={position}
				title={`${port.name}: ${port.type}`}
				style={{
					top: getPortTop(index, ports.length),
					backgroundColor: PORT_TYPE_COLOR[port.type],
					borderColor: 'rgb(39 39 42)',
					height: 10,
					width: 10,
				}}
				className='transition-transform duration-150 group-hover:scale-125'
			/>
		))}
	</>
);

const BaseNode = ({ data, selected }: NodeProps<TCanvasNode>) => {
	const def = getNodeDefinition(data.defKey, data.definition);
	const hue = HUE_TO_CLASSES[def?.color ?? 'zinc'] ?? HUE_TO_CLASSES.zinc;
	const status = data.status ?? 'idle';
	const inputs = def?.inputs ?? [];
	const outputs = def?.outputs ?? [];
	const validationIssues = (data.validationIssues ?? []) as TValidationIssue[];
	const hasError =
		status === 'error' || validationIssues.some((issue) => issue.severity === 'error');
	const hasWarning = validationIssues.length > 0;
	const isActiveRunNode = Boolean(data.isActiveRunNode);

	const statusClass: Record<string, string> = {
		idle: 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
		queued: 'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
		running: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
		success: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
		error: 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300',
		skipped: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
	};

	const NodeIcon = iconMap[data.defKey as keyof typeof iconMap];

	return (
		<motion.div
			whileHover={{ y: -2 }}
			transition={{ duration: 0.16 }}
			className={[
				'group relative w-[248px] rounded-xl border p-3 text-left shadow-2xl transition-all duration-200',
				'bg-white/95 text-zinc-950 backdrop-blur-xl dark:bg-zinc-950/92 dark:text-zinc-100',
				selected
					? 'border-emerald-300/80 ring-4 shadow-emerald-100/60 ring-emerald-300/20 dark:border-emerald-300/70 dark:shadow-emerald-950/40 dark:ring-emerald-400/20'
					: `border-zinc-200 ${hue.darkBorder}`,
				isActiveRunNode ? 'ring-4 shadow-emerald-500/20 ring-emerald-400/20' : '',
				hasError ? 'border-rose-400/80 ring-4 ring-rose-500/10' : '',
			].join(' ')}>
			<div className='pointer-events-none absolute inset-x-4 top-0 h-px bg-gradient-to-r from-transparent via-zinc-300 to-transparent dark:via-white/25' />
			<PortHandles ports={inputs} type='target' position={Position.Left} />
			{hasWarning && (
				<div
					title={validationIssues.map((issue) => issue.message).join('\n')}
					className={[
						'absolute -top-2 -right-2 z-10 flex h-7 w-7 items-center justify-center rounded-full border shadow transition-transform duration-200 group-hover:scale-110',
						hasError
							? 'border-rose-200 bg-rose-500 text-white'
							: 'border-amber-200 bg-amber-400 text-zinc-950',
					].join(' ')}>
					<TriangleAlert size={14} />
				</div>
			)}
			<div className='flex items-start gap-2'>
				<span
					className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border text-xs font-black transition-transform duration-200 ${hue.bg} ${hue.text} ${hue.border} ${hue.darkBg} ${hue.darkText} ${hue.darkBorder} group-hover:scale-105`}>
					{NodeIcon ? <NodeIcon size={18} /> : (def?.icon ?? '?')}
				</span>
				<div className='min-w-0 flex-1'>
					<div className='truncate text-sm font-semibold tracking-tight'>
						{data.label}
					</div>
					<div className='mt-0.5 line-clamp-2 text-xs leading-4 text-zinc-500'>
						{def?.description}
					</div>
				</div>
			</div>
			<div className='mt-3 flex items-center justify-between gap-2'>
				<div className='flex items-center gap-1.5' title={`${inputs.length} input ports`}>
					<span className='text-[10px] font-black text-zinc-400'>{inputs.length}</span>
					{inputs.slice(0, 3).map((port) => (
						<span
							key={port.id}
							className='h-2.5 w-2.5 rounded-full border border-zinc-800 transition-transform duration-150 group-hover:scale-125 dark:border-zinc-600'
							style={{ backgroundColor: PORT_TYPE_COLOR[port.type] }}
							title={`${port.name}: ${port.type}`}
						/>
					))}
				</div>
				<span
					className={[
						'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase transition-all duration-200',
						statusClass[status],
						isActiveRunNode ? 'ring-2 ring-emerald-400/50' : '',
					].join(' ')}>
					{status === 'running' && <Loader2 size={10} className='animate-spin' />}
					{status === 'success' && <CheckCircle2 size={10} />}
					{status}
				</span>
				<div className='flex items-center gap-1.5' title={`${outputs.length} output ports`}>
					{outputs.slice(0, 3).map((port) => (
						<span
							key={port.id}
							className='h-2.5 w-2.5 rounded-full border border-zinc-800 transition-transform duration-150 group-hover:scale-125 dark:border-zinc-600'
							style={{ backgroundColor: PORT_TYPE_COLOR[port.type] }}
							title={`${port.name}: ${port.type}`}
						/>
					))}
					<span className='text-[10px] font-black text-zinc-400'>{outputs.length}</span>
				</div>
			</div>
			{typeof data.durationMs === 'number' && (
				<div className='mt-2 truncate text-[10px] font-bold text-zinc-400'>
					Last run {data.durationMs}ms
				</div>
			)}
			{isActiveRunNode && (
				<div className='absolute inset-0 -z-10 rounded-xl bg-emerald-400/15 blur-xl' />
			)}
			<PortHandles ports={outputs} type='source' position={Position.Right} />
		</motion.div>
	);
};

export default BaseNode;
