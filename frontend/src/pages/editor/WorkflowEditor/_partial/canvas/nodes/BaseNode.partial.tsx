import { Handle, Position, type NodeProps } from '@xyflow/react';
import { getNodeDefinition } from '../../../_helper/nodeCatalog.constants';
import { HUE_TO_CLASSES, PORT_TYPE_COLOR } from '../../../_helper/builder.constants';
import type { TCanvasNode } from '../../../_types/canvas.type';
import type { TNodePort } from '../../../_types/node.type';
import type { TValidationIssue } from '../../../_helper/validation.helper';

const getPortTop = (index: number, total: number) => `${((index + 1) * 100) / (total + 1)}%`;

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

	return (
		<div
			className={[
				'group relative w-[230px] rounded-lg border p-3 text-left shadow-lg transition-all duration-200',
				'bg-white text-zinc-950 dark:bg-zinc-900 dark:text-zinc-100',
				selected
					? 'border-emerald-400 shadow-xl ring-4 ring-emerald-400/25'
					: `${hue.border} ${hue.darkBorder}`,
				isActiveRunNode
					? 'animate-pulse ring-4 shadow-emerald-500/30 ring-emerald-400/30'
					: '',
				hasError ? 'border-rose-400' : '',
			].join(' ')}>
			<PortHandles ports={inputs} type='target' position={Position.Left} />
			{hasWarning && (
				<div
					title={validationIssues.map((issue) => issue.message).join('\n')}
					className={[
						'absolute -top-2 -right-2 z-10 flex h-6 w-6 items-center justify-center rounded-full border text-[11px] font-black shadow transition-transform duration-200 group-hover:scale-110',
						hasError
							? 'border-rose-200 bg-rose-500 text-white'
							: 'border-amber-200 bg-amber-400 text-zinc-950',
					].join(' ')}>
					!
				</div>
			)}
			<div className='flex items-start gap-2'>
				<span
					className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-md border text-xs font-black transition-transform duration-200 ${hue.bg} ${hue.text} ${hue.border} ${hue.darkBg} ${hue.darkText} ${hue.darkBorder} group-hover:scale-105`}>
					{def?.icon ?? '?'}
				</span>
				<div className='min-w-0 flex-1'>
					<div className='truncate text-sm font-bold'>{data.label}</div>
					<div className='line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400'>
						{def?.description}
					</div>
				</div>
			</div>
			<div className='mt-3 flex items-center justify-between gap-2'>
				<div className='flex items-center gap-1' title={`${inputs.length} input ports`}>
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
						'rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase transition-all duration-200',
						statusClass[status],
						isActiveRunNode ? 'ring-2 ring-emerald-400/50' : '',
					].join(' ')}>
					{status}
				</span>
				<div className='flex items-center gap-1' title={`${outputs.length} output ports`}>
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
			<PortHandles ports={outputs} type='source' position={Position.Right} />
		</div>
	);
};

export default BaseNode;
