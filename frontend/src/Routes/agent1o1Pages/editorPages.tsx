import pages from '@/Routes/pages';
import { lazy } from 'react';

const WorkflowEditorPage = lazy(() => import('@/pages/editor/WorkflowEditor/WorkflowEditor.page'));

const EditorPages = [
	{
		path: pages.editor.to,
		children: [
			{
				path: pages.editor.subPages.new.to,
				element: <WorkflowEditorPage />,
			},
			{
				path: pages.editor.subPages.edit.to,
				element: <WorkflowEditorPage />,
			},
			{
				path: `${pages.editor.subPages.edit.to}/:workspaceId/:workflowId`,
				element: <WorkflowEditorPage />,
			},
		],
	},
];

export default EditorPages;
