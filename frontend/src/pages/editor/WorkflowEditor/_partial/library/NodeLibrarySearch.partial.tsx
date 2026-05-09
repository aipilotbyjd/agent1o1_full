const NodeLibrarySearch = ({
	value,
	onChange,
}: {
	value: string;
	onChange: (value: string) => void;
}) => (
	<div className='border-b border-zinc-200 p-3 dark:border-zinc-800'>
		<input
			value={value}
			onChange={(event) => onChange(event.target.value)}
			placeholder='Search nodes'
			className='w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 transition outline-none placeholder:text-zinc-500 focus:border-emerald-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500'
		/>
	</div>
);

export default NodeLibrarySearch;
