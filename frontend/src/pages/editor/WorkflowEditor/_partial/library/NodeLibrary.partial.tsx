import { useMemo, useState } from 'react';
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

	const groups = isApiUnavailable ? NODE_GROUPS.map((g) => ({
		id: g.category,
		label: g.meta.label,
		color: g.meta.color,
		order: g.meta.order,
		nodes: g.nodes,
	})) : apiGroups;
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
		<aside className='flex h-full w-full shrink-0 flex-col border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950'>
			<div className='flex h-14 items-center justify-between border-b border-zinc-200 px-4 dark:border-zinc-800'>
				<div>
					<div className='text-sm font-black text-zinc-900 dark:text-white'>
						Node Library
					</div>
					<div className='text-xs text-zinc-500'>
						{apiIsLoading ? 'Loading nodes...' : `${totalNodes} building blocks`}
					</div>
				</div>
				<button
					type='button'
					onClick={() => dispatch({ type: 'TOGGLE_LEFT_PANEL' })}
					className='rounded-md border border-zinc-200 px-2 py-1 text-xs text-zinc-500 hover:text-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:text-white'>
					Hide
				</button>
			</div>
			<NodeLibrarySearch value={query} onChange={setQuery} />
			{apiIsLoading && (
				<div className='border-b border-blue-200 bg-blue-50 px-4 py-2 text-xs font-medium text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-300'>
					Loading node categories...
				</div>
			)}
			{isApiUnavailable && !apiIsLoading && (
				<div className='border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300'>
					Using local node definitions (API unavailable)
				</div>
			)}
			<div className='min-h-0 flex-1 overflow-y-auto'>
				{!apiIsLoading && filteredGroups.length === 0 && (
					<div className='flex flex-col items-center justify-center px-4 py-12 text-center'>
						<div className='mb-2 text-sm text-zinc-500 dark:text-zinc-400'>
							No node categories available
						</div>
						<div className='text-xs text-zinc-400 dark:text-zinc-500'>
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
