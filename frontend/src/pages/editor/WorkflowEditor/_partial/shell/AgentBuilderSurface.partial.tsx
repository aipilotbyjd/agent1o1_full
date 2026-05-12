import { motion } from 'framer-motion';
import {
	ArrowUp,
	Bot,
	CalendarDays,
	ChevronsLeft,
	FileText,
	Flame,
	Menu,
	Paperclip,
	PanelTop,
	Share2,
	Sparkles,
	SquarePen,
	Users,
} from 'lucide-react';
import { useWorkflowShellStore } from '@/store/workflowShell.store';

const templateTabs = [
	'All',
	'Sales & Outreach',
	'Data & Analytics',
	'Support & Success',
	'Marketing & Content',
	'Productivity & Ops',
	'Research & Intelligence',
];

const templates = [
	{
		title: 'Recruiting Sourcer',
		copy: 'A recruiting agent. Give it a job description and it finds matching candidates, scores them against the role, and drafts outreach.',
		icons: [Flame, PanelTop],
		count: '+1',
	},
	{
		title: 'Feedback Digest Agent',
		copy: 'A feedback summarizer. It reads through support tickets, groups feedback by theme, and prepares a product-ready brief.',
		icons: [Bot, FileText],
		count: '',
	},
	{
		title: 'Weekly Recap Agent',
		copy: "A weekly recap agent. Every Friday it reviews tickets, meetings, and accomplishments, then writes a team update.",
		icons: [Users, CalendarDays],
		count: '+2',
	},
];

const AgentBuilderSurface = () => {
	const toggleMobileSidebar = useWorkflowShellStore((store) => store.toggleMobileSidebar);

	return (
	<div className='flex min-w-0 flex-1 flex-col bg-white text-zinc-950'>
		<header className='flex h-14 shrink-0 items-center justify-between gap-4 border-b border-zinc-200 px-4 sm:h-15 sm:px-6 lg:justify-end'>
			<button
				type='button'
				onClick={toggleMobileSidebar}
				aria-label='Open sidebar'
				className='flex h-9 w-9 items-center justify-center rounded-xl text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-950 lg:hidden'>
				<Menu size={19} />
			</button>
			<button className='flex items-center gap-2 text-sm font-bold text-zinc-900 transition hover:text-emerald-600'>
				<Share2 size={18} />
				Share
			</button>
			<button className='hidden h-9 w-9 items-center justify-center rounded-xl text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-950 lg:flex'>
				<ChevronsLeft size={18} />
			</button>
		</header>

		<main className='min-h-0 flex-1 overflow-y-auto'>
			<motion.div
				initial={{ y: 18, opacity: 0 }}
				animate={{ y: 0, opacity: 1 }}
				transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
				className='mx-auto flex min-h-full w-full max-w-[1080px] flex-col px-5 pt-10 pb-5 sm:px-8 sm:pt-12 lg:px-10 lg:pt-14'>
				<section>
					<div className='mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-zinc-950 text-white shadow-xl shadow-zinc-200 sm:h-18 sm:w-18 lg:h-20 lg:w-20'>
						<Bot size={38} strokeWidth={2.2} />
					</div>
					<h1 className='text-3xl leading-tight font-semibold tracking-tight text-zinc-950 sm:text-4xl lg:text-[42px] lg:leading-none'>
						Build your agent
					</h1>
					<p className='mt-3 max-w-2xl text-base leading-6 font-medium text-zinc-500 sm:text-lg lg:text-xl'>
						Choose an agent template or simply describe what you need to get started.
					</p>
				</section>

				<section className='mt-12 sm:mt-14 lg:mt-16'>
					<div className='mb-5 flex items-center justify-between gap-4'>
						<h2 className='text-xl font-bold tracking-tight sm:text-2xl'>Templates</h2>
						<button className='shrink-0 text-sm font-bold text-zinc-500 transition hover:text-zinc-950 sm:text-base'>
							Don't show again
						</button>
					</div>

					<div className='flex gap-5 overflow-x-auto border-b border-zinc-200 text-sm font-bold whitespace-nowrap text-zinc-500 sm:gap-6 sm:text-base'>
						{templateTabs.map((tab, index) => (
							<button
								key={tab}
								className={[
									'pb-4 transition hover:text-zinc-950',
									index === 0 ? 'border-b-2 border-zinc-950 text-zinc-950' : '',
								].join(' ')}>
								{tab}
							</button>
						))}
					</div>

					<div className='mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5'>
						{templates.map((template) => (
							<button
								key={template.title}
								className='group min-h-[158px] rounded-2xl border border-zinc-200 bg-white p-4 text-left shadow-sm shadow-zinc-200/60 transition hover:-translate-y-1 hover:border-zinc-300 hover:shadow-xl hover:shadow-zinc-200/80 sm:min-h-[176px] sm:p-5 lg:min-h-[184px]'>
								<h3 className='text-lg font-bold tracking-tight text-zinc-950 sm:text-xl'>
									{template.title}
								</h3>
								<p className='mt-4 line-clamp-3 text-sm leading-6 font-medium text-zinc-500 sm:text-base'>
									{template.copy}
								</p>
								<div className='mt-5 flex items-center'>
									<div className='flex overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50'>
										{template.icons.map((Icon, iconIndex) => (
											<span
												key={`${template.title}-${iconIndex}`}
												className='flex h-9 w-9 items-center justify-center border-r border-zinc-200 last:border-r-0'>
												<Icon
													size={18}
													className={
														iconIndex === 0 ? 'text-emerald-500' : 'text-sky-500'
													}
												/>
											</span>
										))}
										{template.count && (
											<span className='flex h-9 min-w-10 items-center justify-center px-2 text-xs font-bold text-zinc-500'>
												{template.count}
											</span>
										)}
									</div>
								</div>
							</button>
						))}
					</div>
				</section>

				<section className='mt-8 lg:mt-10'>
					<div className='rounded-2xl border border-zinc-200 bg-white shadow-xl shadow-zinc-200/70'>
						<textarea
							placeholder='Send a message to your agent'
							className='h-34 w-full resize-none rounded-t-2xl bg-transparent p-5 text-base leading-7 font-medium outline-none placeholder:text-zinc-400 sm:h-38 sm:text-lg'
						/>
						<div className='flex items-center justify-between px-5 pb-5'>
							<div className='flex items-center gap-3'>
								<button className='flex h-10 w-10 items-center justify-center rounded-xl text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-950'>
									<Paperclip size={20} />
								</button>
								<button className='flex h-10 items-center gap-2 rounded-xl px-2 text-sm font-bold text-zinc-950 transition hover:bg-zinc-100 sm:text-base'>
									<SquarePen size={19} />
									Skill
								</button>
							</div>
							<button className='flex h-11 w-11 items-center justify-center rounded-full bg-[linear-gradient(135deg,#10b981,#38bdf8,#ec4899)] text-white shadow-lg shadow-emerald-500/20 ring-4 ring-white transition hover:-translate-y-0.5'>
								<ArrowUp size={21} />
							</button>
						</div>
					</div>
					<div className='mt-4 text-center text-sm font-medium text-zinc-400 sm:text-base'>
						Having trouble?{' '}
						<button className='font-semibold text-zinc-500 underline underline-offset-4 transition hover:text-zinc-950'>
							Report your issue to our team
						</button>
					</div>
				</section>

				<div className='pointer-events-none mt-auto h-6' />
			</motion.div>
		</main>

		<div className='pointer-events-none absolute right-10 bottom-8 hidden items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-700 xl:flex'>
			<Sparkles size={16} />
			Agent draft ready
		</div>
	</div>
	);
};

export default AgentBuilderSurface;
