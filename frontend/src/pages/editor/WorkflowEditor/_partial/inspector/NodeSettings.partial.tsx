import { useMemo } from 'react';
import { getNodeDefinition } from '../../_helper/nodeCatalog.constants';
import { collectUpstreamVariables } from '../../_helper/variables.helper';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import type { TNodeField } from '../../_types/node.type';

const inputClass =
	'w-full rounded-lg border bg-white px-3 py-2 text-sm text-zinc-900 outline-none transition border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20';

const FieldInput = ({
	field,
	value,
	onChange,
}: {
	field: TNodeField;
	value: unknown;
	onChange: (value: unknown) => void;
}) => {
	if (field.kind === 'toggle') {
		const active = Boolean(value);
		return (
			<button
				type='button'
				role='switch'
				aria-checked={active}
				onClick={() => onChange(!active)}
				aria-label={field.label}
				className={`h-7 w-12 rounded-full border p-1 transition ${active ? 'border-emerald-400 bg-emerald-500' : 'border-zinc-300 bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-800'} `}>
				<span
					className={`block h-4 w-4 rounded-full bg-white transition ${active ? 'translate-x-5' : ''}`}
				/>
			</button>
		);
	}

	if (field.kind === 'select' || field.kind === 'model' || field.kind === 'credential') {
		return (
			<select
				value={String(value ?? '')}
				onChange={(event) => onChange(event.target.value)}
				aria-label={field.label}
				className={inputClass}>
				<option value=''>Select...</option>
				{field.options?.map((option) => (
					<option key={option.value} value={option.value}>
						{option.label}
					</option>
				))}
			</select>
		);
	}

	if (field.kind === 'multiselect') {
		const selectedValues = Array.isArray(value) ? value : [];
		return (
			<select
				multiple
				size={4}
				value={selectedValues}
				onChange={(event) => {
					const options = Array.from(event.target.selectedOptions);
					onChange(options.map((opt) => opt.value));
				}}
				aria-label={field.label}
				className={`${inputClass} h-auto`}>
				{field.options?.map((option) => (
					<option key={option.value} value={option.value}>
						{option.label}
					</option>
				))}
			</select>
		);
	}

	if (field.kind === 'number') {
		return (
			<input
				type='number'
				value={Number(value ?? 0)}
				onChange={(event) => onChange(Number(event.target.value))}
				aria-label={field.label}
				className={inputClass}
			/>
		);
	}

	if (field.kind === 'longtext' || field.kind === 'code') {
		return (
			<textarea
				rows={field.rows ?? 4}
				value={String(value ?? '')}
				onChange={(event) => onChange(event.target.value)}
				placeholder={field.placeholder}
				aria-label={field.label}
				className={`${inputClass} font-mono text-xs`}
			/>
		);
	}

	if (field.kind === 'kv') {
		const kvPairs = Array.isArray(value) ? value : [{ key: '', value: '' }];
		return (
			<div className='space-y-2'>
				{kvPairs.map((pair, index) => (
					<div key={index} className='grid grid-cols-2 gap-2'>
						<input
							type='text'
							placeholder='Key'
							value={pair.key || ''}
							onChange={(e) => {
								const newPairs = [...kvPairs];
								newPairs[index] = { ...pair, key: e.target.value };
								onChange(newPairs);
							}}
							className={inputClass}
							aria-label={`${field.label} key ${index + 1}`}
						/>
						<input
							type='text'
							placeholder='Value'
							value={pair.value || ''}
							onChange={(e) => {
								const newPairs = [...kvPairs];
								newPairs[index] = { ...pair, value: e.target.value };
								onChange(newPairs);
							}}
							className={inputClass}
							aria-label={`${field.label} value ${index + 1}`}
						/>
					</div>
				))}
				<button
					type='button'
					onClick={() => onChange([...kvPairs, { key: '', value: '' }])}
					className='text-xs text-emerald-600 hover:text-emerald-700 dark:text-emerald-400'>
					+ Add row
				</button>
			</div>
		);
	}

	return (
		<input
			value={String(value ?? '')}
			onChange={(event) => onChange(event.target.value)}
			placeholder={field.placeholder}
			aria-label={field.label}
			className={inputClass}
		/>
	);
};

const NodeSettings = ({ nodeId }: { nodeId: string }) => {
	const { state, dispatch } = useWorkflowEditor();
	const node = state.nodes.find((item) => item.id === nodeId);
	const def = node ? getNodeDefinition(node.data.defKey, node.data.definition) : null;
	const variables = useMemo(
		() => (node ? collectUpstreamVariables(node.id, state.nodes, state.edges) : []),
		[node, state.edges, state.nodes],
	);

	if (!node || !def) return null;

	// Count required fields and current valid fields
	const requiredFields = def.fields.filter((f) => f.required);
	const validRequiredFields = requiredFields.filter((f) => {
		const v = node.data.values[f.key];
		return v !== undefined && v !== null && v !== '';
	});

	return (
		<div className='space-y-4'>
			<div className='mb-4 flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/30'>
				<div className='text-xs'>
					<span className='font-black text-emerald-700 dark:text-emerald-300'>
						{validRequiredFields.length} / {requiredFields.length}
					</span>
					<span className='ml-1 text-emerald-600 dark:text-emerald-400'>required</span>
				</div>
				{requiredFields.length > 0 &&
					validRequiredFields.length === requiredFields.length && (
						<span className='text-xs font-bold text-emerald-600 dark:text-emerald-400'>
							✓ Complete
						</span>
					)}
			</div>

			<div>
				<label
					htmlFor={`node-name-${nodeId}`}
					className='mb-1 block text-xs font-black tracking-widest text-zinc-500 uppercase dark:text-zinc-400'>
					Node name
				</label>
				<input
					id={`node-name-${nodeId}`}
					value={node.data.label}
					onChange={(event) =>
						dispatch({ type: 'RENAME_NODE', id: node.id, label: event.target.value })
					}
					className={inputClass}
					aria-label='Node name'
				/>
			</div>

			{def.fields.map((field) => {
				const fieldValue = node.data.values[field.key];
				const isRequired = field.required && !fieldValue;

				return (
					<div key={field.key}>
						<div className='mb-1 flex items-baseline justify-between'>
							<label className='block text-xs font-black tracking-widest text-zinc-500 uppercase dark:text-zinc-400'>
								{field.label}
								{field.required && <span className='ml-0.5 text-rose-500'>*</span>}
							</label>
							{isRequired && (
								<span className='text-xs text-rose-500' aria-live='polite'>
									Required
								</span>
							)}
						</div>

						<FieldInput
							field={field}
							value={fieldValue}
							onChange={(value) =>
								dispatch({
									type: 'UPDATE_NODE_VALUE',
									id: node.id,
									fieldKey: field.key,
									value,
								})
							}
						/>

						{field.help && (
							<p className='mt-1.5 text-xs text-zinc-500 dark:text-zinc-400'>
								{field.help}
							</p>
						)}

						{field.supportsVariables && variables.length > 0 && (
							<div
								className='mt-2 flex flex-wrap gap-1'
								aria-label='Available variables'>
								{variables.slice(0, 6).map((variable) => (
									<span
										key={`${variable.nodeId}:${variable.outputId}`}
										className='rounded bg-zinc-200 px-2 py-1 text-[10px] text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'>
										{variable.token}
									</span>
								))}
							</div>
						)}
					</div>
				);
			})}
		</div>
	);
};

export default NodeSettings;
