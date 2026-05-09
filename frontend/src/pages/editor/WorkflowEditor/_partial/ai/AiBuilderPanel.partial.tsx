import { useMemo, useState } from 'react';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import { getAiWorkflowDraft, type TAiWorkflowDraft } from '../../_helper/aiWorkflowDraft.helper';
import Icon from '@/components/icon/Icon';

type TAiChatMessage = {
	id: string;
	role: 'assistant' | 'user';
	text: string;
	draft?: TAiWorkflowDraft;
};

const quickPrompts = [
	'Summarize a website and email the result',
	'Extract JSON from support tickets',
	'Classify leads and notify Slack',
];

const agentActions = [
	{
		icon: 'AiEditing',
		title: 'Improve flow',
		description: 'Find missing steps and better node order.',
		prompt: 'Improve the current workflow and add missing output steps',
	},
	{
		icon: 'BubbleChatPreview',
		title: 'Review setup',
		description: 'Check inputs, outputs, and risky settings.',
		prompt: 'Validate this workflow and suggest safer node settings',
	},
];

const makeMessageId = () =>
	`ai_msg_${Math.random().toString(36).slice(2, 8)}_${Date.now().toString(36)}`;

const starterMessages: TAiChatMessage[] = [
	{
		id: 'welcome',
		role: 'assistant',
		text: 'Tell me what you want this workflow to do. I can draft the flow, suggest nodes, and keep the canvas editable.',
	},
];

const AiBuilderPanel = () => {
	const { state, dispatch } = useWorkflowEditor();
	const [messages, setMessages] = useState<TAiChatMessage[]>(starterMessages);
	const [prompt, setPrompt] = useState('');
	const [isThinking, setIsThinking] = useState(false);

	const nodeSummary = useMemo(
		() =>
			state.nodes.length
				? `${state.nodes.length} nodes, ${state.edges.length} connections`
				: 'Empty canvas',
		[state.edges.length, state.nodes.length],
	);

	if (!state.ui.aiPanelOpen) return null;

	const addAssistantDraft = (text: string) => {
		const draft = getAiWorkflowDraft(text);
		setMessages((current) => [
			...current,
			{ id: makeMessageId(), role: 'user', text },
			{
				id: makeMessageId(),
				role: 'assistant',
				text: `${draft.summary} I can place this on the canvas now, then you can edit every node manually.`,
				draft,
			},
		]);
	};

	const sendPrompt = () => {
		const cleanPrompt = prompt.trim();
		if (!cleanPrompt || isThinking) return;
		setPrompt('');
		setIsThinking(true);
		window.setTimeout(() => {
			addAssistantDraft(cleanPrompt);
			setIsThinking(false);
		}, 260);
	};

	const buildDraft = (draft: TAiWorkflowDraft) => {
		dispatch({
			type: 'ADD_TEMPLATE',
			name: draft.name,
			defKeys: draft.defKeys,
		});
	};

	const resetChat = () => {
		setPrompt('');
		setIsThinking(false);
		setMessages(starterMessages);
	};

	return (
		<aside className='flex h-full w-full flex-col border-r border-zinc-200 bg-zinc-50 text-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100'>
			<div className='shrink-0 border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950'>
				<div className='flex items-center justify-between gap-3'>
					<div className='flex min-w-0 items-center gap-3'>
						<div className='flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-zinc-950 text-lg text-white dark:bg-white dark:text-zinc-950'>
							<Icon icon='AiMagic' />
						</div>
						<div className='min-w-0'>
							<div className='truncate text-sm font-black text-zinc-900 dark:text-white'>
								AI Builder
							</div>
							<div className='mt-0.5 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400'>
								<span className='inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500' />
								<span className='truncate'>{nodeSummary}</span>
							</div>
						</div>
					</div>
					<div className='flex shrink-0 items-center gap-2'>
						<button
							type='button'
							onClick={resetChat}
							className='flex h-8 items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-2.5 text-xs font-bold text-zinc-600 shadow-sm hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white'>
							<Icon icon='Reload' className='text-sm' />
							New
						</button>
						<button
							type='button'
							onClick={() => dispatch({ type: 'TOGGLE_AI_PANEL' })}
							title='Collapse AI builder'
							aria-label='Collapse AI builder'
							className='flex h-8 w-8 items-center justify-center rounded-lg border border-zinc-200 bg-white text-sm text-zinc-500 shadow-sm hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white'>
							<Icon icon='SidebarLeft' />
						</button>
					</div>
				</div>
			</div>

			<div className='flex min-h-0 flex-1 flex-col'>
				<div className='border-b border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950'>
					<div className='grid grid-cols-2 gap-2'>
						{agentActions.map((action) => (
							<button
								key={action.title}
								type='button'
								onClick={() => addAssistantDraft(action.prompt)}
								className='group rounded-xl border border-zinc-200 bg-white p-3 text-left shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-emerald-700 dark:hover:bg-emerald-950/20'>
								<div className='mb-2 flex h-8 w-8 items-center justify-center rounded-lg bg-zinc-100 text-base text-zinc-600 group-hover:bg-emerald-100 group-hover:text-emerald-700 dark:bg-zinc-800 dark:text-zinc-300 dark:group-hover:bg-emerald-950 dark:group-hover:text-emerald-300'>
									<Icon icon={action.icon} />
								</div>
								<div className='text-xs font-black text-zinc-900 dark:text-white'>
									{action.title}
								</div>
								<div className='mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400'>
									{action.description}
								</div>
							</button>
						))}
					</div>
				</div>

				<div className='min-h-0 flex-1 space-y-5 overflow-y-auto p-4'>
					{messages.map((message) => (
						<div
							key={message.id}
							className={`flex items-start gap-2 ${message.role === 'user' ? 'flex-row-reverse' : ''}`}>
							<div
								className={[
									'mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm',
									message.role === 'user'
										? 'bg-emerald-500 text-white'
										: 'border border-zinc-200 bg-white text-zinc-700 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200',
								].join(' ')}>
								<Icon icon={message.role === 'user' ? 'AiUser' : 'AiMagic'} />
							</div>
							<div
								className={[
									'max-w-[82%] rounded-2xl px-3.5 py-3 text-sm leading-6 shadow-sm',
									message.role === 'user'
										? 'rounded-tr-md bg-emerald-500 text-white shadow-emerald-500/10'
										: 'rounded-tl-md border border-zinc-200 bg-white text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200',
								].join(' ')}>
								<div className='mb-1 text-[11px] font-black tracking-widest uppercase opacity-60'>
									{message.role === 'user' ? 'You' : 'Agent'}
								</div>
								<div>{message.text}</div>
								{message.draft && (
									<div className='mt-3 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200'>
										<div className='border-b border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900'>
											<div className='flex items-start gap-2'>
												<div className='flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300'>
													<Icon icon='TaskAdd01' />
												</div>
												<div className='min-w-0'>
													<div className='truncate text-xs font-black text-zinc-900 dark:text-white'>
														{message.draft.name}
													</div>
													<div className='mt-1 text-xs text-zinc-500 dark:text-zinc-400'>
														Ready to draft on canvas
													</div>
												</div>
											</div>
										</div>
										<div className='flex flex-wrap gap-1.5 p-3'>
											{message.draft.defKeys.map((key) => (
												<span
													key={key}
													className='rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 text-[10px] font-black text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400'>
													{key}
												</span>
											))}
										</div>
										<button
											type='button'
											onClick={() =>
												buildDraft(message.draft as TAiWorkflowDraft)
											}
											className='mx-3 mb-3 flex h-9 w-[calc(100%-1.5rem)] items-center justify-center gap-2 rounded-lg bg-emerald-500 px-3 text-xs font-black text-white hover:bg-emerald-600'>
											<Icon icon='TaskAdd01' className='text-sm' />
											Build on canvas
										</button>
									</div>
								)}
							</div>
						</div>
					))}
					{isThinking && (
						<div className='flex items-start gap-2'>
							<div className='mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-zinc-200 bg-white text-sm text-zinc-700 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200'>
								<Icon icon='AiMagic' />
							</div>
							<div className='rounded-2xl rounded-tl-md border border-zinc-200 bg-white px-3.5 py-3 text-sm text-zinc-500 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400'>
								<span className='inline-flex items-center gap-2'>
									<span className='h-1.5 w-1.5 rounded-full bg-emerald-500' />
									Thinking through nodes...
								</span>
							</div>
						</div>
					)}
				</div>

				<div className='shrink-0 border-t border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950'>
					<div className='mb-2 flex gap-2 overflow-x-auto pb-1'>
						{quickPrompts.map((quickPrompt) => (
							<button
								key={quickPrompt}
								type='button'
								onClick={() => addAssistantDraft(quickPrompt)}
								className='shrink-0 rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs font-bold text-zinc-600 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-emerald-700 dark:hover:bg-emerald-950/20 dark:hover:text-emerald-300'>
								{quickPrompt}
							</button>
						))}
					</div>
					<div className='rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm focus-within:border-emerald-300 focus-within:ring-4 focus-within:ring-emerald-500/10 dark:border-zinc-800 dark:bg-zinc-900 dark:focus-within:border-emerald-700'>
						<textarea
							value={prompt}
							onChange={(event) => setPrompt(event.target.value)}
							onKeyDown={(event) => {
								if (event.key === 'Enter' && !event.shiftKey) {
									event.preventDefault();
									sendPrompt();
								}
							}}
							aria-label='Message AI builder'
							placeholder='Ask AI to build, modify, or review this workflow'
							className='max-h-36 min-h-20 w-full resize-none bg-transparent px-2 py-1 text-sm leading-6 text-zinc-900 outline-none placeholder:text-zinc-500 dark:text-zinc-100'
						/>
						<div className='flex items-center justify-between gap-2'>
							<div className='flex items-center gap-1'>
								<button
									type='button'
									title='Attach context'
									aria-label='Attach context'
									className='flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white'>
									<Icon icon='Attachment01' />
								</button>
								<button
									type='button'
									title='Use current canvas'
									aria-label='Use current canvas'
									className='flex h-8 w-8 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white'>
									<Icon icon='BubbleChat' />
								</button>
							</div>
							<button
								type='button'
								onClick={sendPrompt}
								disabled={!prompt.trim() || isThinking}
								className='flex h-9 items-center gap-2 rounded-lg bg-zinc-950 px-3 text-xs font-black text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200'>
								<Icon icon='DeliverySent02' className='text-sm' />
								Send
							</button>
						</div>
					</div>
				</div>
			</div>
		</aside>
	);
};

export default AiBuilderPanel;
