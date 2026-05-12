import { useEffect, useMemo, useRef, useState } from 'react';
import { motion } from 'framer-motion';
import {
	ArrowUp,
	Bell,
	Bot,
	Cable,
	CalendarDays,
	Check,
	ChevronDown,
	ChevronLeft,
	ChevronRight,
	ChevronsUpDown,
	Copy,
	ExternalLink,
	FolderOpen,
	Grid2X2,
	Link2,
	Maximize,
	Menu,
	MessageSquare,
	Minus,
	Paperclip,
	Play,
	Plus,
	Save,
	Search,
	Settings2,
	Share2,
	SlidersHorizontal,
	Sparkles,
	Table2,
	UploadCloud,
	UserRound,
	Webhook,
	Zap,
} from 'lucide-react';
import { useWorkflowShellStore } from '@/store/workflowShell.store';

const promptExamples = [
	"For an email, enrich the contact with Apollo, get recent news about the company and have AI analyze whether it's a fit for my business",
	'Build a workflow that reads a webhook, validates the payload, and alerts the team when intent is high',
	'Before each sales call, research the account, summarize context, and draft next steps in Slack',
];

const quickSteps = [
	{ label: 'Webhook', icon: Webhook },
	{ label: 'AI Agent', icon: Bot },
	{ label: 'Trigger', icon: Zap },
	{ label: 'Message', icon: MessageSquare },
];

const startOptions = [
	{
		label: 'Start with a trigger',
		description: 'Choose an event that kicks off the workflow.',
		icon: Zap,
		accent: 'text-emerald-500',
	},
	{
		label: 'Start with an integration',
		description: 'Connect an app, API, database, or service first.',
		icon: Grid2X2,
		accent: 'text-sky-500',
	},
];

const triggerOptions = [
	{
		label: 'Create a Time Trigger',
		description: 'Schedule this workflow for a date, interval, or recurring window.',
		icon: CalendarDays,
		tone: 'rose',
	},
	{
		label: 'Google Drive Folder Reader',
		description: 'Watch a folder and process newly added files automatically.',
		icon: FolderOpen,
		tone: 'blue',
	},
	{
		label: 'Google Sheets Reader',
		description: 'Read rows from a spreadsheet and run steps for each update.',
		icon: Table2,
		tone: 'green',
	},
	{
		label: 'Google Calendar Event Reader',
		description: 'Trigger from upcoming events, attendees, and meeting windows.',
		icon: CalendarDays,
		tone: 'sky',
	},
	{
		label: 'Gmail Reader',
		description: 'Scan unread emails from a mailbox and extract useful context.',
		icon: MessageSquare,
		tone: 'red',
	},
	{
		label: 'Slack Message Reader',
		description: 'Read recent channel messages and route them into automation.',
		icon: MessageSquare,
		tone: 'pink',
	},
	{
		label: 'Teams Message Reader',
		description: 'Use Teams channel activity as a workflow starting point.',
		icon: MessageSquare,
		tone: 'violet',
	},
];

const triggerToneClasses: Record<string, string> = {
	rose: 'border-rose-200 bg-rose-50 text-rose-500',
	blue: 'border-blue-200 bg-blue-50 text-blue-600',
	green: 'border-emerald-200 bg-emerald-50 text-emerald-600',
	sky: 'border-sky-200 bg-sky-50 text-sky-600',
	red: 'border-red-200 bg-red-50 text-red-600',
	pink: 'border-pink-200 bg-pink-50 text-pink-600',
	violet: 'border-violet-200 bg-violet-50 text-violet-600',
};

const WorkflowLaunchSurface = () => {
	const toggleMobileSidebar = useWorkflowShellStore((store) => store.toggleMobileSidebar);
	const fileInputRef = useRef<HTMLInputElement>(null);
	const [mode, setMode] = useState<'build' | 'ask'>('ask');
	const [exampleIndex, setExampleIndex] = useState(0);
	const [typedText, setTypedText] = useState('');
	const [hasUserEditedPrompt, setHasUserEditedPrompt] = useState(false);
	const [preferencesOpen, setPreferencesOpen] = useState(false);
	const [attachedFileName, setAttachedFileName] = useState('');
	const [surfaceMode, setSurfaceMode] = useState<'launch' | 'interface'>('launch');
	const [copiedInterfaceUrl, setCopiedInterfaceUrl] = useState(false);
	const [triggerPickerOpen, setTriggerPickerOpen] = useState(false);
	const [triggerSearch, setTriggerSearch] = useState('');
	const [selectedTrigger, setSelectedTrigger] = useState(triggerOptions[0]);
	const activePrompt = promptExamples[exampleIndex];
	const actionLabel = useMemo(() => (mode === 'build' ? 'Build workflow' : 'Ask AI'), [mode]);
	const interfaceUrl = 'https://agent1o1.app/interface/lead-routing-draft';
	const filteredTriggers = useMemo(
		() =>
			triggerOptions.filter((trigger) =>
				`${trigger.label} ${trigger.description}`
					.toLowerCase()
					.includes(triggerSearch.trim().toLowerCase()),
			),
		[triggerSearch],
	);

	const handleCopyInterfaceUrl = async () => {
		await navigator.clipboard?.writeText(interfaceUrl);
		setCopiedInterfaceUrl(true);
		window.setTimeout(() => setCopiedInterfaceUrl(false), 1600);
	};

	useEffect(() => {
		if (hasUserEditedPrompt) return undefined;

		let charIndex = 0;
		let cycleTimer: number | undefined;
		setTypedText('');

		const typingTimer = window.setInterval(() => {
			charIndex += 1;
			setTypedText(activePrompt.slice(0, charIndex));

			if (charIndex >= activePrompt.length) {
				window.clearInterval(typingTimer);
				cycleTimer = window.setTimeout(
					() => setExampleIndex((current) => (current + 1) % promptExamples.length),
					2200,
				);
			}
		}, 24);

		return () => {
			window.clearInterval(typingTimer);
			if (cycleTimer) window.clearTimeout(cycleTimer);
		};
	}, [activePrompt, hasUserEditedPrompt]);

	return (
	<div className='relative flex min-w-0 flex-1 overflow-hidden bg-[#fbfbfc] text-zinc-950'>
		<div
			className='absolute inset-0'
			style={{
				backgroundImage:
					'radial-gradient(circle at 1px 1px, rgba(113, 113, 122, 0.34) 1.6px, transparent 0)',
				backgroundSize: '96px 96px',
			}}
		/>
		<div className='pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_42%,rgba(16,185,129,0.08),transparent_34%),linear-gradient(180deg,rgba(255,255,255,0.92),rgba(255,255,255,0.72))]' />

		<div className='relative z-10 flex h-full min-w-0 flex-1 flex-col'>
			<div className='flex items-start justify-between gap-3 p-3 sm:p-4 lg:p-5'>
				<motion.div
					initial={{ y: -12, opacity: 0 }}
					animate={{ y: 0, opacity: 1 }}
					className='flex h-13 max-w-[760px] min-w-0 items-center gap-2 rounded-2xl border border-zinc-200/80 bg-white/95 px-3 shadow-sm shadow-zinc-200/70 backdrop-blur-xl sm:h-14 sm:gap-3 sm:px-4'>
					<button
						type='button'
						onClick={toggleMobileSidebar}
						aria-label='Open sidebar'
						className='flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-950 lg:hidden'>
						<Menu size={18} />
					</button>
					<div className='flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-zinc-950 text-white'>
						<Sparkles size={18} />
					</div>
					<div className='min-w-0 pr-1'>
						<div className='truncate text-base font-semibold tracking-tight sm:text-lg'>
							New workflow
						</div>
						<div className='hidden truncate text-xs font-medium text-zinc-500 sm:block'>
							Draft canvas
						</div>
					</div>
					<div className='hidden h-8 w-px bg-zinc-200 md:block' />
					<button
						type='button'
						onClick={() => setSurfaceMode('interface')}
						className={[
							'hidden h-9 items-center gap-2 rounded-xl border px-3 text-sm font-semibold shadow-xs transition hover:-translate-y-0.5 hover:shadow-md md:flex',
							surfaceMode === 'interface'
								? 'border-emerald-200 bg-emerald-50 text-emerald-700'
								: 'border-zinc-200 bg-white hover:border-zinc-300',
						].join(' ')}>
						<Cable size={17} />
						{surfaceMode === 'interface' ? 'Edit interface' : 'Add interface'}
					</button>
					<button
						type='button'
						onClick={() => setTriggerPickerOpen(true)}
						className='hidden h-9 items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 text-sm font-semibold shadow-xs transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md md:flex'>
						<Zap size={17} />
						Add trigger
					</button>
					<button className='flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-zinc-200 bg-white shadow-xs transition hover:-translate-y-0.5 hover:shadow-md'>
						<Webhook size={17} />
					</button>
					<button className='hidden h-9 w-9 items-center justify-center rounded-xl text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 sm:flex'>
						<Bell size={16} />
					</button>
				</motion.div>

				<motion.div
					initial={{ y: -12, opacity: 0 }}
					animate={{ y: 0, opacity: 1 }}
					transition={{ delay: 0.05 }}
					className='flex h-13 items-center gap-1 rounded-2xl border border-zinc-200/80 bg-white/95 p-1.5 shadow-sm shadow-zinc-200/70 backdrop-blur-xl sm:h-14 sm:gap-2 sm:p-2'>
					<button className='hidden h-9 items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 text-sm font-semibold text-zinc-600 transition hover:text-zinc-950 sm:flex'>
						<Share2 size={16} />
						Share
					</button>
					<div className='flex overflow-hidden rounded-xl border border-zinc-200 bg-white'>
						<button className='flex h-9 items-center gap-2 px-3 text-sm font-semibold text-zinc-600 transition hover:bg-zinc-50 hover:text-zinc-950'>
							<Save size={16} />
							<span className='hidden sm:inline'>Save</span>
						</button>
						<button className='hidden h-9 w-9 items-center justify-center border-l border-zinc-200 text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-950 sm:flex'>
							<ChevronDown size={16} />
						</button>
					</div>
					<button className='flex h-9 items-center gap-2 rounded-xl bg-emerald-500 px-3 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition hover:-translate-y-0.5 hover:bg-emerald-600 sm:px-4'>
						<Play size={16} fill='currentColor' />
						<span className='hidden sm:inline'>Run</span>
					</button>
				</motion.div>
			</div>

			<button
				type='button'
				onClick={() => setSurfaceMode('interface')}
				className='absolute top-24 left-4 z-20 flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-xl shadow-emerald-500/30 transition hover:-translate-y-0.5 hover:bg-emerald-600 sm:top-26 sm:left-5 sm:h-12 sm:w-12'>
				<Plus size={20} />
			</button>
			<button className='absolute top-24 right-4 z-20 flex h-11 w-11 items-center justify-center rounded-2xl border border-zinc-200 bg-white text-zinc-600 shadow-sm transition hover:text-zinc-950 hover:shadow-md sm:top-26 sm:right-5 sm:h-12 sm:w-12'>
				<Grid2X2 size={18} />
			</button>
			<button className='absolute top-1/2 left-1 z-20 hidden h-12 w-7 -translate-y-1/2 items-center justify-center rounded-full text-zinc-400 transition hover:bg-white hover:text-zinc-800 hover:shadow-sm sm:flex'>
				<ChevronRight size={21} />
			</button>

			<div className='flex min-h-0 flex-1 items-center justify-center px-4 pt-14 pb-22 sm:px-6 sm:pt-10 lg:px-8 lg:pb-24'>
				{surfaceMode === 'launch' ? (
					<motion.div
						key='workflow-launch'
						initial={{ scale: 0.96, opacity: 0, y: 18 }}
						animate={{ scale: 1, opacity: 1, y: 0 }}
						transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
						className='w-full max-w-[760px]'>
						<div className='rounded-[24px] bg-[linear-gradient(120deg,rgba(16,185,129,0.25),rgba(14,165,233,0.18),rgba(236,72,153,0.22))] p-1 shadow-xl shadow-zinc-300/70 sm:rounded-[28px]'>
						<div className='overflow-hidden rounded-[20px] border border-zinc-200 bg-white shadow-[inset_0_-1px_0_rgba(24,24,27,0.04)] sm:rounded-[24px]'>
							{selectedTrigger && (
								<div className='flex items-center gap-3 border-b border-zinc-100 bg-zinc-50/70 px-5 py-3 sm:px-6'>
									<div
										className={[
											'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border',
											triggerToneClasses[selectedTrigger.tone],
										].join(' ')}>
										<selectedTrigger.icon size={17} />
									</div>
									<div className='min-w-0 flex-1'>
										<div className='truncate text-sm font-bold text-zinc-900'>
											{selectedTrigger.label}
										</div>
										<div className='truncate text-xs font-medium text-zinc-500'>
											Trigger attached
										</div>
									</div>
									<button
										type='button'
										onClick={() => setTriggerPickerOpen(true)}
										className='rounded-xl border border-zinc-200 bg-white px-3 py-1.5 text-xs font-bold text-zinc-600 shadow-xs transition hover:bg-zinc-50'>
										Change
									</button>
								</div>
							)}
							<div className='relative min-h-36 px-5 pt-5 pb-4 sm:min-h-38 sm:px-6 sm:pt-6'>
								<textarea
									aria-label='Workflow prompt'
									value={typedText}
									onFocus={() => setHasUserEditedPrompt(true)}
									onChange={(event) => {
										setHasUserEditedPrompt(true);
										setTypedText(event.target.value);
									}}
									placeholder='Describe the workflow you want to build...'
									className='h-24 w-full resize-none appearance-none border-0 bg-transparent pr-14 text-lg leading-7 font-medium text-zinc-600 outline-none ring-0 placeholder:text-zinc-400 focus:border-0 focus:outline-none focus:ring-0 sm:text-xl sm:leading-8 lg:text-[22px]'
								/>
								{!hasUserEditedPrompt && typedText.length > 24 && (
									<div className='pointer-events-none absolute top-5 right-5 rounded-lg border border-zinc-200 bg-white px-2 py-0.5 text-xs font-semibold text-zinc-500 shadow-xs sm:text-sm'>
										Tab
									</div>
								)}
								{attachedFileName && (
									<div className='mt-2 inline-flex max-w-full items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700'>
										<span className='truncate'>{attachedFileName}</span>
									</div>
								)}
								<input
									ref={fileInputRef}
									type='file'
									className='hidden'
									onChange={(event) =>
										setAttachedFileName(event.target.files?.[0]?.name ?? '')
									}
								/>
							</div>
							<div className='flex items-center justify-between gap-3 px-5 pb-5 sm:px-6'>
								<div className='relative flex min-w-0 items-center gap-1.5 sm:gap-2'>
									<button
										type='button'
										onClick={() => fileInputRef.current?.click()}
										className='flex h-9 w-9 items-center justify-center rounded-xl text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-950'
										title='Attach file'>
										<Paperclip size={17} />
									</button>
									<button
										type='button'
										onClick={() => setPreferencesOpen((open) => !open)}
										className='flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-500 shadow-xs transition hover:text-zinc-950'
										title='Workflow preferences'>
										<SlidersHorizontal size={16} />
									</button>
									{preferencesOpen && (
										<motion.div
											initial={{ opacity: 0, y: 8, scale: 0.96 }}
											animate={{ opacity: 1, y: 0, scale: 1 }}
											className='absolute bottom-12 left-0 z-30 w-64 rounded-2xl border border-zinc-200 bg-white p-3 text-left shadow-2xl shadow-zinc-300/70'>
											<div className='mb-2 px-1 text-xs font-bold tracking-wide text-zinc-400 uppercase'>
												Preferences
											</div>
											{[
												'Use reliable steps',
												'Ask before publishing',
												'Show advanced nodes',
											].map((preference, index) => (
												<label
													key={preference}
													className='flex cursor-pointer items-center justify-between rounded-xl px-2 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50'>
													<span>{preference}</span>
													<input
														type='checkbox'
														defaultChecked={index < 2}
														className='h-4 w-4 accent-emerald-500'
													/>
												</label>
											))}
										</motion.div>
									)}
									{quickSteps.map((step) => {
										const Icon = step.icon;
										return (
											<button
												key={step.label}
											className='hidden h-9 w-9 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 transition hover:bg-zinc-200 hover:text-zinc-950 sm:flex'
												title={step.label}>
												<Icon size={14} />
											</button>
										);
									})}
								</div>
								<div className='flex items-center gap-2'>
									<div className='flex items-center rounded-2xl bg-zinc-100 p-1 shadow-inner shadow-zinc-200/50'>
									<button
										type='button'
										onClick={() => setMode('build')}
										className={[
											'rounded-xl px-3 py-1.5 text-sm font-semibold transition sm:px-4 sm:py-2 sm:text-base',
											mode === 'build'
												? 'bg-white text-zinc-950 shadow-md shadow-zinc-200/70'
												: 'text-zinc-500 hover:text-zinc-800',
										].join(' ')}>
										Build
									</button>
									<button
										type='button'
										onClick={() => setMode('ask')}
										className={[
											'rounded-xl px-3 py-1.5 text-sm font-semibold transition sm:px-4 sm:py-2 sm:text-base',
											mode === 'ask'
												? 'bg-white text-zinc-950 shadow-md shadow-zinc-200/70'
												: 'text-zinc-500 hover:text-zinc-800',
										].join(' ')}>
										Ask
									</button>
									</div>
									<button
										title={actionLabel}
										className='flex h-10 w-10 items-center justify-center rounded-2xl bg-[linear-gradient(135deg,#10b981,#38bdf8,#ec4899)] text-white shadow-lg shadow-emerald-500/20 ring-4 ring-white transition hover:-translate-y-0.5 sm:h-11 sm:w-11'>
										<ArrowUp size={18} />
									</button>
								</div>
							</div>
						</div>
					</div>

					<div className='mt-6 flex items-center gap-4 text-zinc-400 sm:mt-8'>
						<div className='h-px flex-1 bg-[linear-gradient(90deg,transparent,rgba(161,161,170,0.45),transparent)]' />
						<div className='text-lg font-medium tracking-wide text-zinc-500 sm:text-xl'>OR</div>
						<div className='h-px flex-1 bg-[linear-gradient(90deg,transparent,rgba(161,161,170,0.45),transparent)]' />
					</div>

					<div className='mt-6 flex justify-center gap-7 sm:gap-12'>
						{startOptions.map((option) => {
							const Icon = option.icon;
							return (
								<button
									key={option.label}
									onClick={() => setTriggerPickerOpen(option.label.includes('trigger'))}
									className='group flex w-34 flex-col items-center text-center transition hover:-translate-y-1 sm:w-40'>
									<div className='flex h-14 w-14 items-center justify-center rounded-2xl border border-zinc-200 bg-white shadow-sm transition group-hover:border-zinc-300 group-hover:shadow-xl group-hover:shadow-zinc-200/80 sm:h-16 sm:w-16'>
										<Icon size={25} className={option.accent} />
									</div>
									<div className='mt-3 text-base leading-6 font-medium text-zinc-600 sm:text-lg'>
										{option.label}
									</div>
									<div className='mt-2 hidden text-sm leading-5 font-medium text-zinc-400 opacity-0 transition group-hover:opacity-100 sm:block'>
										{option.description}
									</div>
								</button>
							);
						})}
					</div>
				</motion.div>
				) : (
					<motion.div
						key='interface-editor'
						initial={{ scale: 0.96, opacity: 0, y: 18 }}
						animate={{ scale: 1, opacity: 1, y: 0 }}
						transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
						className='w-full max-w-[680px]'>
						<div className='overflow-hidden rounded-[28px] border border-zinc-200 bg-white shadow-2xl shadow-zinc-300/70'>
							<div className='border-b border-zinc-200 bg-zinc-50/80 px-6 py-7 text-center sm:px-8 sm:py-9'>
								<div className='mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-zinc-950 shadow-sm ring-1 ring-zinc-200'>
									<UploadCloud size={30} strokeWidth={2.2} />
								</div>
								<h2 className='text-2xl font-bold tracking-tight text-zinc-950 sm:text-3xl'>
									Edit interface
								</h2>
								<p className='mx-auto mt-2 max-w-md text-sm font-medium text-zinc-500 sm:text-base'>
									Create a shareable front door for this workflow with a focused form
									and branded entry point.
								</p>
							</div>

							<div className='p-5 sm:p-7'>
								<div className='grid gap-5 sm:grid-cols-[120px_1fr] sm:items-start'>
									<button
										type='button'
										className='group flex h-24 w-24 flex-col items-center justify-center rounded-3xl border border-zinc-200 bg-zinc-50 text-sm font-bold text-zinc-500 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 hover:shadow-md sm:h-28 sm:w-28'>
										<UploadCloud
											size={23}
											className='mb-2 transition group-hover:-translate-y-0.5'
										/>
										Upload icon
									</button>

									<div className='min-w-0 space-y-3'>
										<input
											aria-label='Interface title'
											defaultValue='Lead intake portal'
											className='w-full border-0 bg-transparent p-0 text-3xl font-bold tracking-tight text-zinc-950 outline-none placeholder:text-zinc-300 focus:ring-0 sm:text-4xl'
											placeholder='Interface title'
										/>
										<textarea
											aria-label='Interface description'
											defaultValue='Collect lead context, qualify intent, and route the request into your workflow.'
											className='h-16 w-full resize-none border-0 bg-transparent p-0 text-base leading-6 font-medium text-zinc-500 outline-none focus:ring-0 sm:text-lg'
											placeholder='Add description...'
										/>
									</div>
								</div>

								<div className='mt-8 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-2'>
									<div className='flex min-h-13 items-center gap-3 rounded-xl bg-white px-3 shadow-xs'>
										<Link2 size={19} className='shrink-0 text-zinc-400' />
										<div className='min-w-0 flex-1 truncate text-sm font-semibold text-zinc-500 sm:text-base'>
											{interfaceUrl}
										</div>
										<button
											type='button'
											onClick={handleCopyInterfaceUrl}
											className='flex h-9 shrink-0 items-center gap-2 rounded-xl border border-zinc-200 px-3 text-sm font-bold text-zinc-700 transition hover:bg-zinc-50'>
											{copiedInterfaceUrl ? <Check size={16} /> : <Copy size={16} />}
											<span className='hidden sm:inline'>
												{copiedInterfaceUrl ? 'Copied' : 'Copy'}
											</span>
										</button>
									</div>
								</div>

								<div className='mt-5 grid gap-3 sm:grid-cols-3'>
									{[
										['Fields', 'Name, email, company'],
										['Theme', 'Light, clean, branded'],
										['Status', 'Draft interface'],
									].map(([label, value]) => (
										<div
											key={label}
											className='rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs'>
											<div className='text-xs font-bold tracking-wide text-zinc-400 uppercase'>
												{label}
											</div>
											<div className='mt-2 text-sm font-bold text-zinc-800'>{value}</div>
										</div>
									))}
								</div>

								<div className='mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between'>
									<button
										type='button'
										onClick={() => setSurfaceMode('launch')}
										className='h-11 rounded-2xl border border-zinc-200 bg-white px-5 text-sm font-bold text-zinc-600 transition hover:bg-zinc-50 hover:text-zinc-950'>
										Back to workflow
									</button>
									<div className='flex gap-3'>
										<button className='h-11 flex-1 rounded-2xl border border-zinc-200 bg-white px-5 text-sm font-bold text-zinc-700 transition hover:bg-zinc-50 sm:flex-none'>
											<ExternalLink size={16} className='mr-2 inline' />
											Preview
										</button>
										<button className='h-11 flex-1 rounded-2xl bg-emerald-500 px-5 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition hover:-translate-y-0.5 hover:bg-emerald-600 sm:flex-none'>
											Save interface
										</button>
									</div>
								</div>
							</div>
						</div>
					</motion.div>
				)}
			</div>
			{triggerPickerOpen && (
				<div className='absolute inset-0 z-40 bg-zinc-950/10 backdrop-blur-[1px]'>
					<motion.div
						initial={{ opacity: 0, x: -24, scale: 0.98 }}
						animate={{ opacity: 1, x: 0, scale: 1 }}
						exit={{ opacity: 0, x: -24, scale: 0.98 }}
						transition={{ duration: 0.2 }}
						className='absolute top-20 left-3 flex max-h-[calc(100%-7rem)] w-[min(92vw,520px)] flex-col overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-2xl shadow-zinc-300/80 sm:top-24 sm:left-6'>
						<div className='flex items-center justify-between border-b border-zinc-100 px-4 py-4 sm:px-5'>
							<div className='flex items-center gap-3'>
								<button
									type='button'
									onClick={() => setTriggerPickerOpen(false)}
									className='flex h-10 w-10 items-center justify-center rounded-2xl text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-950'>
									<ChevronLeft size={21} />
								</button>
								<div className='flex h-11 w-11 items-center justify-center rounded-2xl border border-zinc-200 bg-white text-zinc-950 shadow-xs'>
									<Zap size={20} />
								</div>
								<div>
									<div className='text-lg font-bold tracking-tight text-zinc-950'>
										Triggers
									</div>
									<div className='text-xs font-semibold text-zinc-400'>
										Choose how this workflow starts
									</div>
								</div>
							</div>
							<button
								type='button'
								onClick={() => setTriggerPickerOpen(false)}
								className='flex h-10 w-10 items-center justify-center rounded-2xl border border-zinc-200 bg-white text-zinc-600 shadow-xs transition hover:bg-zinc-50 hover:text-zinc-950'>
								<Plus size={18} className='rotate-45' />
							</button>
						</div>

						<div className='border-b border-zinc-100 p-4 sm:p-5'>
							<label className='flex h-12 items-center gap-3 rounded-2xl border border-zinc-300 bg-white px-4 shadow-xs focus-within:border-emerald-300 focus-within:ring-4 focus-within:ring-emerald-100'>
								<Search size={20} className='text-zinc-400' />
								<input
									value={triggerSearch}
									onChange={(event) => setTriggerSearch(event.target.value)}
									placeholder='Search triggers'
									className='min-w-0 flex-1 border-0 bg-transparent text-base font-medium text-zinc-800 outline-none placeholder:text-zinc-400 focus:ring-0'
									autoFocus
								/>
							</label>
						</div>

						<div className='min-h-0 flex-1 overflow-y-auto p-3 sm:p-4'>
							{filteredTriggers.map((trigger, index) => {
								const Icon = trigger.icon;
								const isSelected = selectedTrigger.label === trigger.label;
								return (
									<button
										key={trigger.label}
										type='button'
										onClick={() => {
											setSelectedTrigger(trigger);
											setTriggerPickerOpen(false);
											setSurfaceMode('launch');
										}}
										className={[
											'group flex w-full gap-4 rounded-2xl p-3 text-left transition hover:bg-zinc-50',
											index === 0 ? 'bg-rose-50/75' : '',
											isSelected ? 'ring-2 ring-emerald-200' : '',
										].join(' ')}>
										<div
											className={[
												'flex h-15 w-15 shrink-0 items-center justify-center rounded-2xl border transition group-hover:scale-[1.03]',
												triggerToneClasses[trigger.tone],
											].join(' ')}>
											<Icon size={27} />
										</div>
										<div className='min-w-0 pt-1'>
											<div className='text-base font-bold text-zinc-950 sm:text-lg'>
												{trigger.label}
											</div>
											<div className='mt-1 line-clamp-2 text-sm leading-5 font-medium text-zinc-500 sm:text-base sm:leading-6'>
												{trigger.description}
											</div>
										</div>
									</button>
								);
							})}
							{filteredTriggers.length === 0 && (
								<div className='py-12 text-center'>
									<div className='mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-400'>
										<Search size={20} />
									</div>
									<div className='mt-3 text-sm font-bold text-zinc-700'>
										No triggers found
									</div>
									<div className='mt-1 text-sm font-medium text-zinc-400'>
										Try searching for Gmail, Sheets, calendar, or time.
									</div>
								</div>
							)}
						</div>
					</motion.div>
				</div>
			)}

			<div className='pointer-events-none absolute right-4 bottom-20 left-4 z-20 flex items-end justify-between sm:right-6 sm:bottom-24 sm:left-6'>
				<button className='pointer-events-auto flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-bold text-emerald-600 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:h-12 sm:px-5'>
					<Sparkles size={17} />
					Ask AI for help
				</button>
				<div className='pointer-events-auto flex items-center gap-2'>
					<div className='hidden h-12 items-center gap-1 rounded-2xl border border-zinc-200 bg-white p-1 shadow-sm md:flex'>
						<button className='flex h-10 w-10 items-center justify-center rounded-xl text-zinc-500 hover:bg-zinc-100'>
							<Minus size={17} />
						</button>
						<div className='min-w-16 text-center text-sm font-bold'>75%</div>
						<button className='flex h-10 w-10 items-center justify-center rounded-xl text-zinc-500 hover:bg-zinc-100'>
							<Plus size={17} />
						</button>
						<button className='flex h-10 w-10 items-center justify-center rounded-xl text-zinc-500 hover:bg-zinc-100'>
							<Maximize size={17} />
						</button>
					</div>
					<div className='flex h-11 items-center gap-1 rounded-2xl border border-zinc-200 bg-white p-1 shadow-sm sm:h-12'>
						<button className='flex h-10 w-10 items-center justify-center rounded-xl text-zinc-500 hover:bg-zinc-100'>
							<Grid2X2 size={17} />
						</button>
						<button className='flex h-10 w-10 items-center justify-center rounded-xl text-zinc-500 hover:bg-zinc-100'>
							<ChevronsUpDown size={17} />
						</button>
						<button className='flex h-10 w-10 items-center justify-center rounded-xl text-zinc-500 hover:bg-zinc-100'>
							<Settings2 size={17} />
						</button>
					</div>
				</div>
			</div>

			<div className='relative z-20 flex h-16 items-center justify-between border-t border-zinc-200 bg-white/95 px-3 shadow-[0_-12px_28px_rgba(24,24,27,0.05)] backdrop-blur-xl sm:h-18'>
				<div className='flex h-full items-center gap-2'>
					<button className='flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-600 sm:h-12 sm:w-12'>
						<Plus size={20} />
					</button>
					<button className='flex h-12 min-w-32 items-center justify-between rounded-2xl border border-zinc-200 bg-white px-4 text-base font-bold shadow-sm sm:h-14 sm:min-w-38 sm:px-5'>
						Flow
						<ChevronDown size={18} className='text-zinc-500' />
					</button>
				</div>
				<div className='flex items-center gap-3 sm:gap-5'>
					<div className='flex items-center gap-3 text-zinc-400'>
						<ChevronLeft size={18} />
						<ChevronRight size={18} />
					</div>
					<div className='hidden items-center gap-3 rounded-2xl px-3 py-2 transition hover:bg-zinc-100 md:flex'>
						<div className='flex h-10 w-10 items-center justify-center rounded-xl bg-zinc-950 text-white'>
							<UserRound size={17} />
						</div>
						<div className='text-right'>
							<div className='text-sm font-bold'>Amaan</div>
							<div className='text-xs font-medium text-zinc-500'>
								beingamaan21@gmail.com
							</div>
						</div>
						<ChevronsUpDown size={16} className='text-zinc-400' />
					</div>
				</div>
			</div>
		</div>
	</div>
	);
};

export default WorkflowLaunchSurface;
