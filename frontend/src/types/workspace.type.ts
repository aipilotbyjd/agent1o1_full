/**
 * Workspace Types
 * Matches Laravel backend from docs/frontend/modules/02-workspace-management.md
 */

export type TWorkspaceRole = 'owner' | 'admin' | 'editor' | 'member' | 'viewer';

// Workspace entity — as returned by GET /api/v1/workspaces
export type TWorkspace = {
	id: string;
	name: string;
	slug: string;
	role: TWorkspaceRole;
	created_at: string;
};

// Workspace detail — as returned by GET /api/v1/workspaces/{id}
export type TWorkspaceDetail = TWorkspace & {
	settings: TWorkspaceSettings;
	members_count: number;
	workflows_count: number;
};

export type TWorkspaceSettings = {
	timezone: string;
	default_workflow_timeout: number;
};

// Workspace member — as returned by GET /workspaces/{id}/members
export type TWorkspaceMember = {
	id: string;
	user_id: string;
	name: string;
	email: string;
	avatar: string | null;
	role: TWorkspaceRole;
	joined_at: string;
	last_active_at?: string;
	workflows_created?: number;
	executions_run?: number;
};

// Workspace invitation — as returned by GET /workspaces/{id}/invitations
export type TWorkspaceInvitation = {
	id: string;
	email: string;
	role: TWorkspaceRole;
	invited_by: string;
	status: 'pending' | 'accepted' | 'declined' | 'expired';
	expires_at: string;
	created_at: string;
};

// ─── Request DTOs ────────────────────────────────────────────

// POST /workspaces
export type TCreateWorkspaceDto = {
	name: string;
	slug: string;
};

// PUT /workspaces/{id}
export type TUpdateWorkspaceDto = {
	name?: string;
	settings?: Partial<TWorkspaceSettings>;
};

// PUT /workspaces/{id}/members/{userId}
export type TUpdateMemberRoleDto = {
	role: TWorkspaceRole;
};

// POST /workspaces/{id}/invitations
export type TSendInvitationDto = {
	email: string;
	role: TWorkspaceRole;
	message?: string;
};
