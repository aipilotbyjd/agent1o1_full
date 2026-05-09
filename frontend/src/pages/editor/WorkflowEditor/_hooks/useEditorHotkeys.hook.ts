import { useEffect } from 'react';
import { useWorkflowEditor } from '../_context/WorkflowEditorProvider.context';
import { useRunWorkflow } from '../_hooks/useRunWorkflow.hook';

const isTypingTarget = (target: EventTarget | null) => {
	if (!(target instanceof HTMLElement)) return false;
	return ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable;
};

export const useEditorHotkeys = () => {
	const { dispatch, state } = useWorkflowEditor();
	const { runWorkflow, stopRun } = useRunWorkflow();

	useEffect(() => {
		const onKeyDown = (event: KeyboardEvent) => {
			const mod = event.metaKey || event.ctrlKey;

			// Undo/Redo
			if (mod && event.key.toLowerCase() === 'z' && !event.shiftKey) {
				event.preventDefault();
				dispatch({ type: 'UNDO' });
				return;
			}
			if (
				(mod && event.shiftKey && event.key.toLowerCase() === 'z') ||
				(mod && event.key.toLowerCase() === 'y')
			) {
				event.preventDefault();
				dispatch({ type: 'REDO' });
				return;
			}

			// Run/Stop workflow
			if (mod && event.key === 'Enter') {
				event.preventDefault();
				if (state.run.status === 'running') {
					stopRun();
				} else {
					runWorkflow();
				}
				return;
			}

			// Command palette
			if (mod && event.key.toLowerCase() === 'p') {
				event.preventDefault();
				dispatch({ type: 'SET_COMMAND_PALETTE', open: true });
				return;
			}

			// AI Builder - use J instead of conflicting with Command Palette
			if (mod && event.key.toLowerCase() === 'j' && !event.shiftKey) {
				event.preventDefault();
				dispatch({ type: 'TOGGLE_AI_PANEL' });
				return;
			}

			// Toggle panels
			if (mod && event.shiftKey && event.key.toLowerCase() === 'l') {
				event.preventDefault();
				dispatch({ type: 'TOGGLE_LEFT_PANEL' });
				return;
			}
			if (mod && event.shiftKey && event.key.toLowerCase() === 'r') {
				event.preventDefault();
				dispatch({ type: 'TOGGLE_RUN_PANEL' });
				return;
			}
			if (mod && event.shiftKey && event.key.toLowerCase() === 'e') {
				event.preventDefault();
				dispatch({ type: 'SET_IMPORT_EXPORT', open: true });
				return;
			}

			// Auto-layout
			if (!isTypingTarget(event.target) && event.key.toLowerCase() === 'l') {
				event.preventDefault();
				dispatch({ type: 'AUTO_LAYOUT' });
				return;
			}

			// Node operations
			if (mod && event.key.toLowerCase() === 'd') {
				event.preventDefault();
				dispatch({ type: 'DUPLICATE_SELECTED' });
				return;
			}
			if (
				!isTypingTarget(event.target) &&
				['Backspace', 'Delete'].includes(event.key) &&
				state.ui.selectedNodeId
			) {
				event.preventDefault();
				dispatch({ type: 'DELETE_SELECTED' });
				return;
			}
		};

		window.addEventListener('keydown', onKeyDown);
		return () => window.removeEventListener('keydown', onKeyDown);
	}, [dispatch, state.run.status, state.ui.selectedNodeId, runWorkflow, stopRun]);
};
