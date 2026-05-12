import {
	Activity,
	Bot,
	ChevronLeft,
	ChevronRight,
	FolderKanban,
	KeyRound,
	Plus,
	Search,
	Settings,
	Sparkles,
	Workflow,
} from 'lucide-react';
import { motion } from 'framer-motion';
import { useWorkflowShellStore } from '@/store/workflowShell.store';

const navigation = [
	{ id: 'workflows', label: 'Workflows', icon: Workflow },
	{ id: 'agents', label: 'Agents', icon: Bot },
	{ id: 'runs', label: 'Run history', icon: Activity },
	{ id: 'secrets', label: 'Secrets', icon: KeyRound },
	{ id: 'settings', label: 'Settings', icon: Settings },
] as const;

const workflows = [
	{ name: 'AI Lead Routing Agent', state: 'Draft', active: true },
	{ name: 'Invoice Extraction', state: 'Live', active: false },
	{ name: 'Support Triage', state: 'Live', active: false },
	{ name: 'Churn Risk Monitor', state: 'Paused', active: false },
];

const WorkspaceSidebar = () => {
	const {
		activeWorkspaceView,
		closeMobileSidebar,
		setActiveWorkspaceView,
		sidebarCollapsed,
		toggleSidebar,
	} =
		useWorkflowShellStore();

	return (
		<motion.aside
			initial={false}
			animate={{ width: sidebarCollapsed ? 76 : 272 }}
			transition={{ duration: 0.22, ease: [0.22, 1, 0.36, 1] }}
			className='flex h-screen shrink-0 flex-col border-r border-zinc-200 bg-white text-zinc-950 shadow-2xl shadow-zinc-200/40 dark:border-white/10 dark:bg-[#07080b] dark:text-zinc-100 dark:shadow-black/30'>
			<div className='flex h-16 items-center gap-3 border-b border-zinc-200 px-4 dark:border-white/10'>
				<div className='flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-300/20 dark:bg-emerald-400/10 dark:text-emerald-200'>
					<Sparkles size={18} strokeWidth={2.2} />
				</div>
				{!sidebarCollapsed && (
					<div className='min-w-0'>
						<div className='truncate text-sm font-semibold tracking-tight'>
							Agent1o1
						</div>
						<div className='truncate text-xs text-zinc-500'>Automation Studio</div>
					</div>
				)}
				<button
					type='button'
					onClick={toggleSidebar}
					aria-label={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
					className='ml-auto flex h-8 w-8 items-center justify-center rounded-lg border border-zinc-200 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-950 dark:border-white/10 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white'>
					{sidebarCollapsed ? <ChevronRight size={15} /> : <ChevronLeft size={15} />}
				</button>
			</div>

			<div className='min-h-0 flex-1 overflow-y-auto px-3 py-4'>
				{!sidebarCollapsed && (
					<div className='mb-4 flex h-9 items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 text-zinc-500 focus-within:border-emerald-300 focus-within:text-zinc-700 dark:border-white/10 dark:bg-white/[0.03] dark:focus-within:border-emerald-400/50 dark:focus-within:text-zinc-300'>
						<Search size={15} />
						<input
							aria-label='Search workflows'
							placeholder='Search workflows'
							className='min-w-0 flex-1 bg-transparent text-sm text-zinc-900 outline-none placeholder:text-zinc-400 dark:text-zinc-200 dark:placeholder:text-zinc-600'
						/>
					</div>
				)}

				<nav className='space-y-1'>
					{navigation.map((item) => {
						const Icon = item.icon;
						const isActive = activeWorkspaceView === item.id;
						return (
							<button
								key={item.id}
								type='button'
								title={item.label}
								onClick={() => {
									setActiveWorkspaceView(item.id);
									closeMobileSidebar();
								}}
								className={[
									'flex h-10 w-full items-center gap-3 rounded-lg px-3 text-sm transition',
									isActive
										? 'bg-zinc-100 text-zinc-950 shadow-sm dark:border dark:border-emerald-300/20 dark:bg-emerald-400/10 dark:text-emerald-100 dark:shadow-lg dark:shadow-emerald-950/20'
										: 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-400 dark:hover:bg-white/[0.06] dark:hover:text-zinc-100',
									sidebarCollapsed ? 'justify-center px-0' : '',
								].join(' ')}>
								<Icon size={17} />
								{!sidebarCollapsed && <span>{item.label}</span>}
							</button>
						);
					})}
				</nav>

				{!sidebarCollapsed && (
					<div className='mt-6'>
						<div className='mb-2 flex items-center justify-between px-2'>
							<span className='text-[11px] font-semibold tracking-[0.16em] text-zinc-500 uppercase dark:text-zinc-600'>
								Workspace
							</span>
							<button
								type='button'
								title='New workflow'
								className='flex h-6 w-6 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-950 dark:hover:bg-white/5 dark:hover:text-white'>
								<Plus size={14} />
							</button>
						</div>
						<div className='space-y-1'>
							{workflows.map((workflow) => (
								<button
									key={workflow.name}
									type='button'
									onClick={() => {
										setActiveWorkspaceView('editor');
										closeMobileSidebar();
									}}
									className={[
										'w-full rounded-lg px-3 py-2 text-left transition',
										workflow.active
											? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-400/10 dark:text-emerald-100 dark:ring-emerald-300/20'
											: 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-400 dark:hover:bg-white/[0.05] dark:hover:text-zinc-100',
									].join(' ')}>
									<div className='flex items-center gap-2'>
										<FolderKanban size={14} />
										<span className='min-w-0 flex-1 truncate text-sm font-medium'>
											{workflow.name}
										</span>
									</div>
									<div className='mt-1 pl-6 text-[11px] text-zinc-600'>
										{workflow.state}
									</div>
								</button>
							))}
						</div>
					</div>
				)}
			</div>

			<div className='border-t border-zinc-200 p-3 dark:border-white/10'>
				<div
					className={[
						'flex items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-2 dark:border-white/10 dark:bg-white/[0.03]',
						sidebarCollapsed ? 'justify-center' : '',
					].join(' ')}>
					<div className='flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white text-xs font-bold text-zinc-950 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-100'>
						SA
					</div>
					{!sidebarCollapsed && (
						<div className='min-w-0'>
							<div className='truncate text-sm font-semibold'>Sahil Amaan</div>
							<div className='truncate text-xs text-zinc-500'>Admin workspace</div>
						</div>
					)}
				</div>
			</div>
		</motion.aside>
	);
};

export default WorkspaceSidebar;
