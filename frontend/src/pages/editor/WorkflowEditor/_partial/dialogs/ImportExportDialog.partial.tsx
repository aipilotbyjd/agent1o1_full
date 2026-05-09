import { useMemo, useState } from 'react';
import { exportWorkflow, parseWorkflowImport } from '../../_helper/importExport.helper';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import Modal from './Modal.partial';

const ImportExportDialog = () => {
	const { state, dispatch } = useWorkflowEditor();
	const exported = useMemo(() => exportWorkflow(state), [state]);
	const [raw, setRaw] = useState(exported);
	const [error, setError] = useState<string | null>(null);

	if (!state.ui.importExportOpen) return null;

	return (
		<Modal
			title='Import / Export Workflow'
			onClose={() => dispatch({ type: 'SET_IMPORT_EXPORT', open: false })}>
			<div className='space-y-3'>
				<textarea
					value={raw}
					onChange={(event) => setRaw(event.target.value)}
					className='h-80 w-full rounded-xl border border-zinc-300 bg-white p-3 font-mono text-xs text-zinc-800 transition outline-none placeholder:text-zinc-500 focus:border-emerald-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200'
				/>
				{error && (
					<div className='rounded-lg bg-rose-500/10 px-3 py-2 text-sm text-rose-300'>
						{error}
					</div>
				)}
				<div className='flex justify-end gap-2'>
					<button
						type='button'
						onClick={() => {
							setRaw(exported);
							setError(null);
						}}
						className='rounded-lg border border-zinc-300 px-3 py-2 text-sm font-bold text-zinc-600 hover:text-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:text-white'>
						Reset
					</button>
					<button
						type='button'
						onClick={() => {
							try {
								dispatch({
									type: 'LOAD_WORKFLOW',
									workflow: parseWorkflowImport(raw),
								});
							} catch (err) {
								setError(
									err instanceof Error ? err.message : 'Invalid workflow JSON.',
								);
							}
						}}
						className='rounded-lg bg-emerald-500 px-3 py-2 text-sm font-black text-white hover:bg-emerald-600'>
						Import
					</button>
				</div>
			</div>
		</Modal>
	);
};

export default ImportExportDialog;
