import { validateWorkflow } from '../../_helper/validation.helper';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import Icon from '@/components/icon/Icon';

const StatusBar = () => {
	const { state } = useWorkflowEditor();
	const issues = validateWorkflow(state.nodes, state.edges);
	const errorCount = issues.filter((i) => i.severity === 'error').length;
	const warningCount = issues.filter((i) => i.severity === 'warning').length;

	return (
		<footer className='flex h-8 shrink-0 items-center justify-between border-t border-zinc-200 bg-white px-3 text-xs dark:border-zinc-800 dark:bg-zinc-950'>
			<div className='flex items-center gap-4'>
				<span className='text-zinc-500 dark:text-zinc-400'>
					{state.workflow.savingState}
				</span>
				{errorCount > 0 && (
					<span className='flex items-center gap-1 text-rose-600 dark:text-rose-400'>
						<Icon icon='AlertCircle' className='text-xs' />
						{errorCount} error{errorCount !== 1 ? 's' : ''}
					</span>
				)}
				{warningCount > 0 && (
					<span className='flex items-center gap-1 text-amber-600 dark:text-amber-400'>
						<Icon icon='AlertTriangle' className='text-xs' />
						{warningCount} warning{warningCount !== 1 ? 's' : ''}
					</span>
				)}
				{issues.length === 0 && state.nodes.length > 0 && (
					<span className='flex items-center gap-1 text-emerald-600 dark:text-emerald-400'>
						<Icon icon='CheckCircle' className='text-xs' />
						Workflow is valid
					</span>
				)}
			</div>
			<span className='text-zinc-500 dark:text-zinc-400'>{state.workflow.folder}</span>
		</footer>
	);
};

export default StatusBar;
