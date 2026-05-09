import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import Icon from '@/components/icon/Icon';

const templates = [
	{
		name: 'Scrape and summarize',
		description: 'Ask for a URL, scrape the page, summarize, and return output.',
		icon: 'Globe',
		color: 'emerald',
		nodes: ['input.ask', 'scrape.url', 'ai.chat', 'output.display'],
		complexity: 'Beginner',
	},
	{
		name: 'Extract structured data',
		description: 'Collect text, extract JSON, then return the result.',
		icon: 'FileCode',
		color: 'violet',
		nodes: ['input.ask', 'ai.extract', 'output.display'],
		complexity: 'Intermediate',
	},
	{
		name: 'Classify and notify',
		description: 'Ask AI to classify input and send a Slack message.',
		icon: 'Notification03',
		color: 'sky',
		nodes: ['input.ask', 'ai.chat', 'int.slack', 'output.display'],
		complexity: 'Advanced',
	},
];

const complexityColors: Record<string, string> = {
	Beginner: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
	Intermediate: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
	Advanced: 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300',
};

const CanvasEmptyState = () => {
	const { dispatch } = useWorkflowEditor();

	return (
		<div className='pointer-events-none absolute inset-0 flex items-center justify-center p-6'>
			<div className='pointer-events-auto w-full max-w-4xl'>
				<div className='mb-5 text-center'>
					<div className='inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-2xl text-white shadow-lg shadow-emerald-500/20'>
						<Icon icon='AiMagic' />
					</div>
					<div className='mt-3 text-2xl font-black text-zinc-900 dark:text-white'>
						Build with AI first
					</div>
					<div className='mt-1 text-sm text-zinc-500 dark:text-zinc-400'>
						Describe the outcome, then refine the generated flow on the canvas.
					</div>
				</div>
				<div className='mb-6 rounded-2xl border border-emerald-200 bg-white p-4 shadow-xl dark:border-emerald-900/60 dark:bg-zinc-900'>
					<div className='flex flex-col gap-3 md:flex-row md:items-center'>
						<div className='min-w-0 flex-1 px-1'>
							<div className='text-sm font-black text-zinc-900 dark:text-white'>
								Ask AI for help
							</div>
							<div className='mt-1 text-xs text-zinc-500 dark:text-zinc-400'>
								Open the AI Builder panel and describe the workflow you want.
							</div>
						</div>
						<button
							type='button'
							onClick={() => dispatch({ type: 'TOGGLE_AI_PANEL' })}
							className='flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-500 px-5 py-2 text-sm font-black text-white hover:bg-emerald-600 md:self-stretch'>
							<Icon icon='AiMagic' className='text-base' />
							Open AI Builder
						</button>
					</div>
				</div>
				<div>
					<div className='mb-3 text-xs font-black tracking-widest text-zinc-500 uppercase dark:text-zinc-400'>
						Quick start templates
					</div>
					<div className='grid gap-3 md:grid-cols-3'>
						{templates.map((template) => (
							<button
								key={template.name}
								type='button'
								onClick={() =>
									dispatch({
										type: 'ADD_TEMPLATE',
										name: template.name,
										defKeys: template.nodes,
									})
								}
								className='group rounded-xl border border-zinc-200 bg-white p-4 text-left shadow-xl transition hover:-translate-y-0.5 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800'>
								<div className='mb-2 flex items-center justify-between'>
									<div
										className={[
											'flex h-10 w-10 items-center justify-center rounded-lg',
											template.color === 'emerald' &&
												'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
											template.color === 'violet' &&
												'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300',
											template.color === 'sky' &&
												'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
										].join(' ')}>
										<Icon icon={template.icon as string} className='text-lg' />
									</div>
									<span
										className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${complexityColors[template.complexity]}`}>
										{template.complexity}
									</span>
								</div>
								<div className='text-sm font-black'>{template.name}</div>
								<div className='mt-1 min-h-10 text-xs text-zinc-500 dark:text-zinc-400'>
									{template.description}
								</div>
								<div className='mt-3 flex gap-1 text-[10px] font-black text-zinc-400 dark:text-zinc-500'>
									{template.nodes.map((node) => (
										<span
											key={node}
											className='rounded bg-zinc-100 px-1.5 py-0.5 dark:bg-zinc-800'>
											{node.split('.')[0]}
										</span>
									))}
								</div>
							</button>
						))}
					</div>
				</div>
			</div>
		</div>
	);
};

export default CanvasEmptyState;
