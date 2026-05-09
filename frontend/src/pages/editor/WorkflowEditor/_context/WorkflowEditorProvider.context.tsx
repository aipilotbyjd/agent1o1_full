import { ReactNode, useContext, useMemo, useReducer } from 'react';
import {
	initialWorkflowEditorState,
	WorkflowEditorContext,
	workflowEditorReducer,
} from './WorkflowEditorStore.context';

export const WorkflowEditorProvider = ({ children }: { children: ReactNode }) => {
	const [state, dispatch] = useReducer(workflowEditorReducer, initialWorkflowEditorState);
	const value = useMemo(() => ({ state, dispatch }), [state]);

	return (
		<WorkflowEditorContext.Provider value={value}>{children}</WorkflowEditorContext.Provider>
	);
};

export const useWorkflowEditor = () => {
	const context = useContext(WorkflowEditorContext);
	if (!context) throw new Error('useWorkflowEditor must be used inside WorkflowEditorProvider');
	return context;
};
