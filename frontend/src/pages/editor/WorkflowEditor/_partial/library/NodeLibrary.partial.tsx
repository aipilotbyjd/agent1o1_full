import { useMemo, useState } from 'react';
import { Box, PanelLeftClose, Search, Sparkles } from 'lucide-react';
import { useNodeCategories } from '@/api/modules/node-types';
import { mapApiCategoriesToGroups } from '../../_helper/apiNodeCatalog.helper';
import { NODE_GROUPS } from '../../_helper/nodeGroups.constants';
import { useWorkflowEditor } from '../../_context/WorkflowEditorProvider.context';
import NodeCategorySection from './NodeCategorySection.partial';
import NodeLibrarySearch from './NodeLibrarySearch.partial';
import TemplateLibrary from './TemplateLibrary.partial';

const NodeLibrary = () => {
	const { state, dispatch } = useWorkflowEditor();
	const [query, setQuery] = useState('');
	const {
		data: apiCategories,
		isLoading: apiIsLoading,
		isError: apiIsError,
	} = useNodeCategories({
		include_nodes: true,
	});

	const apiGroups = useMemo(
		() => (apiCategories?.length ? mapApiCategoriesToGroups(apiCategories) : []),
		[apiCategories],
	);

	const isApiUnavailable = apiIsError || (!apiIsLoading && !apiCategories?.length);

	const groups = isApiUnavailable
		? NODE_GROUPS.map((g) => ({
				id: g.category,
				label: g.meta.label,
				color: g.meta.color,
				order: g.meta.order,
				nodes: g.nodes,
			}))
		: apiGroups;
	const totalNodes = groups.reduce((count, group) => count + group.nodes.length, 0);

	const filteredGroups = useMemo(() => {
		const needle = query.trim().toLowerCase();
		if (!needle) return groups;

		return groups
			.map((group) => ({
				...group,
				nodes: group.nodes.filter((node) =>
					[node.label, node.description, node.category, node.key].some((value) =>
						value?.toLowerCase().includes(needle),
					),
				),
			}))
			.filter((group) => group.nodes.length);
	}, [groups, query]);

	if (!state.ui.leftPanelOpen) return null;

	return (
		<aside className='flex h-full w-full shrink-0 flex-col border-r border-zinc-200 bg-white text-zinc-950 backdrop-blur-xl dark:border-white/10 dark:bg-zinc-950/96 dark:text-zinc-100'>
			<div className='flex h-16 items-center justify-between border-b border-zinc-200 px-4 dark:border-white/10'>
				<div>
					<div className='flex items-center gap-2 text-sm font-semibold text-zinc-950 dark:text-white'>
						<Box size={15} />
						Nodes
					</div>
					<div className='mt-0.5 text-xs text-zinc-500'>
						{apiIsLoading ? 'Loading nodes...' : `${totalNodes} building blocks`}
					</div>
				</div>
				<button
					type='button'
					onClick={() => dispatch({ type: 'TOGGLE_LEFT_PANEL' })}
					title='Hide node library'
					className='flex h-8 w-8 items-center justify-center rounded-lg border border-zinc-200 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-950 dark:border-white/10 dark:hover:bg-white/[0.06] dark:hover:text-white'>
					<PanelLeftClose size={15} />
				</button>
			</div>
			<div className='border-b border-zinc-200 p-3 dark:border-white/10'>
				<div className='flex h-10 items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 text-zinc-500 focus-within:border-emerald-300 focus-within:text-zinc-700 dark:border-white/10 dark:bg-white/[0.03] dark:focus-within:border-emerald-300/40 dark:focus-within:text-zinc-300'>
					<Search size={15} />
					<NodeLibrarySearch value={query} onChange={setQuery} />
				</div>
			</div>
			{apiIsLoading && (
				<div className='space-y-2 border-b border-zinc-200 px-4 py-3 dark:border-white/10'>
					<div className='h-3 w-2/3 animate-pulse rounded bg-zinc-200 dark:bg-white/10' />
					<div className='h-3 w-1/2 animate-pulse rounded bg-zinc-200 dark:bg-white/10' />
				</div>
			)}
			{isApiUnavailable && !apiIsLoading && (
				<div className='flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-700 dark:border-amber-300/10 dark:bg-amber-300/5 dark:text-amber-100'>
					<Sparkles size={13} />
					Local premium node catalog
				</div>
			)}
			<div className='min-h-0 flex-1 overflow-y-auto'>
				{!apiIsLoading && filteredGroups.length === 0 && (
					<div className='flex flex-col items-center justify-center px-4 py-12 text-center'>
						<div className='mb-2 text-sm text-zinc-400'>
							No node categories available
						</div>
						<div className='text-xs text-zinc-600'>
							Check your API connection or contact support
						</div>
					</div>
				)}
				{filteredGroups.map((group) => (
					<NodeCategorySection
						key={group.id}
						label={group.label}
						color={group.color}
						nodes={group.nodes}
						onAdd={(node) =>
							dispatch({
								type: 'ADD_NODE',
								defKey: node.key,
								definition: node,
								position: { x: 120, y: 120 },
							})
						}
					/>
				))}
			</div>
			<TemplateLibrary />
		</aside>
	);
};

export default NodeLibrary;
