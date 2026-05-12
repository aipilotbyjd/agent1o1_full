import { create } from 'zustand';

type TWorkflowShellState = {
	sidebarCollapsed: boolean;
	mobileSidebarOpen: boolean;
	activeWorkspaceView: 'workflows' | 'agents' | 'editor' | 'runs' | 'secrets' | 'settings';
	setActiveWorkspaceView: (view: TWorkflowShellState['activeWorkspaceView']) => void;
	closeMobileSidebar: () => void;
	toggleMobileSidebar: () => void;
	toggleSidebar: () => void;
};

export const useWorkflowShellStore = create<TWorkflowShellState>((set) => ({
	sidebarCollapsed: false,
	mobileSidebarOpen: false,
	activeWorkspaceView: 'workflows',
	setActiveWorkspaceView: (view) => set({ activeWorkspaceView: view }),
	closeMobileSidebar: () => set({ mobileSidebarOpen: false }),
	toggleMobileSidebar: () => set((state) => ({ mobileSidebarOpen: !state.mobileSidebarOpen })),
	toggleSidebar: () => set((state) => ({ sidebarCollapsed: !state.sidebarCollapsed })),
}));
