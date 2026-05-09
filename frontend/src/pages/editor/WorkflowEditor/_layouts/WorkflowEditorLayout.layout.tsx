import { ReactNode } from 'react';
import { ReactFlowProvider } from '@xyflow/react';
import { WorkflowEditorProvider } from '../_context/WorkflowEditorProvider.context';

const WorkflowEditorLayout = ({ children }: { children: ReactNode }) => (
	<WorkflowEditorProvider>
		<ReactFlowProvider>
			<div className='flex h-screen w-screen flex-col overflow-hidden bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100'>
				{children}
			</div>
		</ReactFlowProvider>
	</WorkflowEditorProvider>
);

export default WorkflowEditorLayout;
