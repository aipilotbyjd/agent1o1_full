<?php

/*
|--------------------------------------------------------------------------
| API Routes — LinkFlow v1
|--------------------------------------------------------------------------
|
| Route layers (outermost → innermost):
|
|   1. Public        — No auth required (health, verify-email, webhooks)
|   2. Guest         — Auth routes for unauthenticated users (login, register)
|   3. Authenticated — Requires valid access token (auth:api)
|   4. Workspace     — Requires membership + resolves role/permissions ONCE
|                      via 'workspace.role' middleware. All nested models are
|                      scoped to the workspace via scopeBindings().
|
| Authorization strategy:
|   - 'workspace.role' middleware loads permissions once per request
|   - Controllers use $this->can(Permission::...) for authorization
|   - Form Requests check permissions in authorize() method
|
*/

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\AgentConversationController;
use App\Http\Controllers\Api\V1\AgentSkillController;
use App\Http\Controllers\Api\V1\AgentTriggerController;
use App\Http\Controllers\Api\V1\AiAutofixController;
use App\Http\Controllers\Api\V1\ArchivedExecutionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ConnectorMetricController;
use App\Http\Controllers\Api\V1\CredentialController;
use App\Http\Controllers\Api\V1\CredentialTypeController;
use App\Http\Controllers\Api\V1\CreditController;
use App\Http\Controllers\Api\V1\ExecutionController;
use App\Http\Controllers\Api\V1\FolderController;
use App\Http\Controllers\Api\V1\GitSyncController;
use App\Http\Controllers\Api\V1\GitSyncWebhookController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\LogStreamingConfigController;
use App\Http\Controllers\Api\V1\NodeCategoryController;
use App\Http\Controllers\Api\V1\NodeController;
use App\Http\Controllers\Api\V1\NodeSandboxController;
use App\Http\Controllers\Api\V1\NotificationChannelController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\OAuthCredentialController;
use App\Http\Controllers\Api\V1\PinnedNodeDataController;
use App\Http\Controllers\Api\V1\PollingTriggerController;
use App\Http\Controllers\Api\V1\SseController;
use App\Http\Controllers\Api\V1\StickyNoteController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VariableController;
use App\Http\Controllers\Api\V1\WaitWebhookController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\WebhookReceiverController;
use App\Http\Controllers\Api\V1\WorkflowApprovalController;
use App\Http\Controllers\Api\V1\WorkflowBuilderController;
use App\Http\Controllers\Api\V1\WorkflowContractController;
use App\Http\Controllers\Api\V1\WorkflowController;
use App\Http\Controllers\Api\V1\WorkflowEnvironmentReleaseController;
use App\Http\Controllers\Api\V1\WorkflowImportExportController;
use App\Http\Controllers\Api\V1\WorkflowShareController;
use App\Http\Controllers\Api\V1\WorkflowTemplateController;
use App\Http\Controllers\Api\V1\WorkflowVersionController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WorkspaceEnvironmentController;
use App\Http\Controllers\Api\V1\WorkspaceMemberController;
use App\Http\Controllers\Api\V1\WorkspaceSettingController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->as('v1.')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Public — No authentication required
    |----------------------------------------------------------------------
    */

    Route::get('health', fn () => response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]))->name('health');

    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], 'webhook/{uuid}', [WebhookReceiverController::class, 'handle'])
        ->middleware(['throttle:webhook-receive', 'ip.allowlist'])
        ->name('webhook.receive');

    // ── Wait-node webhook resume ─────────────────────────────────
    // Called by external systems to resume a workflow paused at a
    // Wait node (mode=webhook). No auth required — the UUID is the secret.
    Route::match(['GET', 'POST', 'PUT', 'PATCH'], 'webhook-wait/{uuid}', [WaitWebhookController::class, 'resume'])
        ->middleware(['throttle:webhook-receive', 'ip.allowlist'])
        ->name('webhook-wait.resume');

    Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
        ->name('stripe.webhook');

    Route::post('git-sync/webhook/{workspaceSlug}', [GitSyncWebhookController::class, 'handle'])
        ->name('git-sync.webhook');

    // ── Shared Workflows (public viewing) ───────────────────────
    Route::get('shared/{token}', [WorkflowShareController::class, 'viewPublic'])->name('shared.view');

    // ── Workflow Templates (Global Catalog) ────────────────────────
    Route::prefix('templates')->as('templates.')->group(function () {
        Route::get('/', [WorkflowTemplateController::class, 'index'])->name('index.public');
        Route::get('{workflowTemplate}', [WorkflowTemplateController::class, 'show'])->name('show.public');
    });

    // ── OAuth2 Callback (no auth — redirect from provider) ──────
    Route::get('oauth/callback', [OAuthCredentialController::class, 'callback'])->name('oauth.callback');

    /*
    |----------------------------------------------------------------------
    | Guest — Authentication routes (unauthenticated users only)
    |----------------------------------------------------------------------
    */

    Route::prefix('auth')
        ->as('auth.')
        ->middleware('throttle:auth')
        ->group(function () {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
            Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
            Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
        });

    /*
    |----------------------------------------------------------------------
    | Authenticated — Requires valid access token
    |----------------------------------------------------------------------
    */

    Route::middleware('auth:api')->group(function () {

        // ── Auth (post-login) ────────────────────────────────────────

        Route::prefix('auth')->as('auth.')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('resend-verification-email', [AuthController::class, 'resendVerificationEmail'])->name('resend-verification');
        });

        // ── User Profile ─────────────────────────────────────────────

        Route::prefix('user')->as('user.')->group(function () {
            Route::get('/', [UserController::class, 'me'])->name('show');
            Route::put('/', [UserController::class, 'update'])->name('update');
            Route::delete('/', [UserController::class, 'destroy'])->name('destroy');
            Route::put('password', [UserController::class, 'changePassword'])->name('password');
            Route::post('avatar', [UserController::class, 'uploadAvatar'])->name('avatar.upload');
            Route::delete('avatar', [UserController::class, 'deleteAvatar'])->name('avatar.delete');
        });

        // ── Notifications ────────────────────────────────────────────

        Route::prefix('notifications')->as('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
            Route::delete('/', [NotificationController::class, 'destroyAll'])->name('destroy-all');
            Route::post('{id}/read', [NotificationController::class, 'markRead'])->name('read');
            Route::delete('{id}', [NotificationController::class, 'destroy'])->name('destroy');
        });

        // ── Notification Preferences ─────────────────────────────────

        Route::prefix('notification-preferences')->as('notification-preferences.')->group(function () {
            Route::get('/', [NotificationPreferenceController::class, 'index'])->name('index');
            Route::put('/', [NotificationPreferenceController::class, 'update'])->name('update');
        });

        // ── Notification Channels (Slack, Discord, Webhook, SMS) ──────

        Route::prefix('notification-channels')->as('notification-channels.')->group(function () {
            Route::get('/', [NotificationChannelController::class, 'index'])->name('index');
            Route::post('/', [NotificationChannelController::class, 'store'])->name('store');
            Route::put('{id}', [NotificationChannelController::class, 'update'])->name('update');
            Route::delete('{id}', [NotificationChannelController::class, 'destroy'])->name('destroy');
            Route::post('{id}/test', [NotificationChannelController::class, 'test'])->name('test');
        });

        // ── Invitations (accept/decline — no workspace context needed) ─

        Route::prefix('invitations/{token}')->as('invitations.')->group(function () {
            Route::post('accept', [InvitationController::class, 'accept'])->name('accept');
            Route::post('decline', [InvitationController::class, 'decline'])->name('decline');
        });

        // ── Workspaces (list + create — no membership needed) ────────

        Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

        /*
        |------------------------------------------------------------------
        | Workspace-Scoped — Requires membership
        |------------------------------------------------------------------
        |
        | Middleware: 'workspace.role'
        |   → Verifies user is a member of the workspace
        |   → Loads role + permissions ONCE, caches on $request->attributes
        |   → Controllers use $this->can(Permission::...) for checks
        |
        | scopeBindings():
        |   → All nested model bindings ({workflow}, {credential}, etc.)
        |     are automatically scoped to {workspace}. Prevents cross-
        |     workspace data access at the routing layer.
        |
        */

        Route::prefix('workspaces/{workspace}')->as('workspaces.')
            ->middleware(['workspace.role'])
            ->scopeBindings()
            ->group(function () {

                // ── Workspace CRUD ───────────────────────────────────

                Route::get('/', [WorkspaceController::class, 'show'])->name('show');
                Route::put('/', [WorkspaceController::class, 'update'])->name('update');
                Route::delete('/', [WorkspaceController::class, 'destroy'])->name('destroy');

                // ── Members ──────────────────────────────────────────

                Route::prefix('members')->as('members.')->group(function () {
                    Route::get('/', [WorkspaceMemberController::class, 'index'])->name('index');
                    Route::put('{user}', [WorkspaceMemberController::class, 'update'])->name('update');
                    Route::delete('{user}', [WorkspaceMemberController::class, 'destroy'])->name('destroy');
                });

                Route::post('transfer-ownership', [WorkspaceMemberController::class, 'transferOwnership'])->name('transfer-ownership');

                Route::post('leave', [WorkspaceMemberController::class, 'leave'])->name('leave');

                // ── Invitations (manage — within workspace) ──────────

                Route::prefix('invitations')->as('invitations.')->group(function () {
                    Route::get('/', [InvitationController::class, 'index'])->name('index');
                    Route::post('/', [InvitationController::class, 'store'])->name('store');
                    Route::delete('{invitation}', [InvitationController::class, 'destroy'])->name('destroy');
                });

                // ── Folders ──────────────────────────────────────────

                Route::prefix('folders')->as('folders.')->group(function () {
                    Route::get('/', [FolderController::class, 'index'])->name('index');
                    Route::post('/', [FolderController::class, 'store'])->name('store');
                    Route::post('move-workflows', [FolderController::class, 'moveWorkflows'])->name('move-workflows');
                    Route::get('{folder}', [FolderController::class, 'show'])->name('show');
                    Route::put('{folder}', [FolderController::class, 'update'])->name('update');
                    Route::delete('{folder}', [FolderController::class, 'destroy'])->name('destroy');
                });

                // ── Workflows ────────────────────────────────────────

                Route::prefix('workflows')->as('workflows.')->group(function () {
                    Route::get('/', [WorkflowController::class, 'index'])->name('index');
                    Route::post('/', [WorkflowController::class, 'store'])->name('store');

                    Route::post('import', [WorkflowImportExportController::class, 'import'])->name('import');
                    Route::post('build', [WorkflowBuilderController::class, 'build'])->name('build');
                    Route::post('bulk-activate', [WorkflowController::class, 'bulkActivate'])->name('bulk-activate');
                    Route::post('bulk-deactivate', [WorkflowController::class, 'bulkDeactivate'])->name('bulk-deactivate');
                    Route::delete('bulk', [WorkflowController::class, 'bulkDestroy'])->name('bulk-destroy');

                    Route::prefix('{workflow}')->group(function () {
                        Route::get('/', [WorkflowController::class, 'show'])->name('show');
                        Route::put('/', [WorkflowController::class, 'update'])->name('update');
                        Route::delete('/', [WorkflowController::class, 'destroy'])->name('destroy');
                        Route::post('activate', [WorkflowController::class, 'activate'])->name('activate');
                        Route::post('deactivate', [WorkflowController::class, 'deactivate'])->name('deactivate');
                        Route::post('lock', [WorkflowController::class, 'lock'])->name('lock');
                        Route::post('unlock', [WorkflowController::class, 'unlock'])->name('unlock');
                        Route::post('duplicate', [WorkflowController::class, 'duplicate'])->name('duplicate');
                        Route::post('execute', [ExecutionController::class, 'store'])->middleware('throttle:execution-trigger')->name('execute');
                        Route::get('executions', [ExecutionController::class, 'workflowExecutions'])->name('executions.index');
                        Route::post('webhook', [WebhookController::class, 'store'])->name('webhook.store');
                        Route::post('polling-trigger', [PollingTriggerController::class, 'store'])->name('polling-trigger.store');
                        Route::get('export', [WorkflowImportExportController::class, 'export'])->name('export');

                        // ── Contracts ─────────────────────────────────

                        Route::prefix('contracts')->as('contracts.')->group(function () {
                            Route::get('/', [WorkflowContractController::class, 'index'])->name('index');
                            Route::post('generate', [WorkflowContractController::class, 'generate'])->name('generate');
                            Route::get('{snapshot}', [WorkflowContractController::class, 'show'])->name('show');
                            Route::get('{snapshot}/test-runs', [WorkflowContractController::class, 'testRuns'])->name('test-runs.index');
                            Route::post('{snapshot}/test-runs', [WorkflowContractController::class, 'runTest'])->name('test-runs.store');
                        });

                        // ── Environment Releases ──────────────────────

                        Route::prefix('releases')->as('releases.')->group(function () {
                            Route::get('/', [WorkflowEnvironmentReleaseController::class, 'index'])->name('index');
                            Route::post('/', [WorkflowEnvironmentReleaseController::class, 'store'])->name('store');
                        });

                        // ── Versions ─────────────────────────────────

                        Route::prefix('versions')->as('versions.')->group(function () {
                            Route::get('/', [WorkflowVersionController::class, 'index'])->name('index');
                            Route::post('/', [WorkflowVersionController::class, 'store'])->name('store');
                            Route::get('diff', [WorkflowVersionController::class, 'diff'])->name('diff');
                            Route::get('{version}', [WorkflowVersionController::class, 'show'])->name('show');
                            Route::post('{version}/publish', [WorkflowVersionController::class, 'publish'])->name('publish');
                            Route::post('{version}/rollback', [WorkflowVersionController::class, 'rollback'])->name('rollback');
                        });

                        // ── Shares ───────────────────────────────────

                        Route::prefix('shares')->as('shares.')->group(function () {
                            Route::get('/', [WorkflowShareController::class, 'index'])->name('index');
                            Route::post('/', [WorkflowShareController::class, 'store'])->name('store');
                            Route::put('{share}', [WorkflowShareController::class, 'update'])->name('update');
                            Route::delete('{share}', [WorkflowShareController::class, 'destroy'])->name('destroy');
                        });

                        // ── Sticky Notes ─────────────────────────────

                        Route::prefix('sticky-notes')->as('sticky-notes.')->group(function () {
                            Route::get('/', [StickyNoteController::class, 'index'])->name('index');
                            Route::post('/', [StickyNoteController::class, 'store'])->name('store');
                            Route::put('{stickyNote}', [StickyNoteController::class, 'update'])->name('update');
                            Route::delete('{stickyNote}', [StickyNoteController::class, 'destroy'])->name('destroy');
                        });

                        // ── Pinned Node Data ─────────────────────────

                        Route::prefix('pinned-data')->as('pinned-data.')->group(function () {
                            Route::get('/', [PinnedNodeDataController::class, 'index'])->name('index');
                            Route::post('/', [PinnedNodeDataController::class, 'store'])->name('store');
                            Route::post('{pinnedData}/toggle', [PinnedNodeDataController::class, 'toggle'])->name('toggle');
                            Route::delete('{pinnedData}', [PinnedNodeDataController::class, 'destroy'])->name('destroy');
                        });
                    });
                });

                // ── Credentials ──────────────────────────────────────

                Route::prefix('credentials')->as('credentials.')->group(function () {
                    Route::get('/', [CredentialController::class, 'index'])->name('index');
                    Route::post('/', [CredentialController::class, 'store'])->name('store');
                    Route::get('{credential}', [CredentialController::class, 'show'])->name('show');
                    Route::put('{credential}', [CredentialController::class, 'update'])->name('update');
                    Route::delete('{credential}', [CredentialController::class, 'destroy'])->name('destroy');
                    Route::post('{credential}/test', [CredentialController::class, 'test'])->name('test');
                    Route::post('{credential}/share', [CredentialController::class, 'share'])->name('share');
                });

                // ── Executions ───────────────────────────────────────

                Route::prefix('executions')->as('executions.')->group(function () {
                    Route::get('stats', [ExecutionController::class, 'stats'])->name('stats');
                    Route::get('compare', [ExecutionController::class, 'compare'])->name('compare');
                    Route::delete('bulk', [ExecutionController::class, 'bulkDestroy'])->name('bulk-destroy');
                    Route::get('stream-all', [SseController::class, 'streamWorkspace'])->name('stream-all');
                    Route::get('/', [ExecutionController::class, 'index'])->name('index');
                    Route::get('{execution}', [ExecutionController::class, 'show'])->name('show');
                    Route::delete('{execution}', [ExecutionController::class, 'destroy'])->name('destroy');
                    Route::get('{execution}/nodes', [ExecutionController::class, 'nodes'])->name('nodes');
                    Route::get('{execution}/logs', [ExecutionController::class, 'logs'])->name('logs');
                    Route::post('{execution}/retry', [ExecutionController::class, 'retry'])->name('retry');
                    Route::post('{execution}/cancel', [ExecutionController::class, 'cancel'])->name('cancel');
                    Route::post('{execution}/replay', [ExecutionController::class, 'replay'])->name('replay');
                    Route::get('{execution}/stream', [SseController::class, 'stream'])->name('stream');
                    Route::post('{execution}/pause', [ExecutionController::class, 'pause'])->name('pause');
                    Route::post('{execution}/resume', [ExecutionController::class, 'resume'])->name('resume');
                    Route::get('{execution}/autofix', [AiAutofixController::class, 'index'])->name('autofix.index');
                    Route::post('{execution}/autofix', [AiAutofixController::class, 'generate'])->name('autofix.generate');
                    Route::post('{execution}/autofix/{suggestion}/apply', [AiAutofixController::class, 'apply'])->name('autofix.apply');

                    // ── Archived Executions ──────────────────────────
                    Route::prefix('archived')->as('archived.')->group(function () {
                        Route::get('/', [ArchivedExecutionController::class, 'index'])->name('index');
                        Route::get('stats', [ArchivedExecutionController::class, 'stats'])->name('stats');
                        Route::get('{execution}', [ArchivedExecutionController::class, 'show'])->name('show');
                        Route::get('{execution}/download', [ArchivedExecutionController::class, 'download'])->name('download');
                        Route::post('{execution}/restore', [ArchivedExecutionController::class, 'restore'])->name('restore');
                    });
                });

                // ── Webhooks ─────────────────────────────────────────

                Route::prefix('webhooks')->as('webhooks.')->group(function () {
                    Route::get('/', [WebhookController::class, 'index'])->name('index');
                    Route::get('{webhook}', [WebhookController::class, 'show'])->name('show');
                    Route::put('{webhook}', [WebhookController::class, 'update'])->name('update');
                    Route::delete('{webhook}', [WebhookController::class, 'destroy'])->name('destroy');
                });

                // ── Polling Triggers ─────────────────────────────────

                Route::prefix('polling-triggers')->as('polling-triggers.')->group(function () {
                    Route::get('/', [PollingTriggerController::class, 'index'])->name('index');
                    Route::get('{pollingTrigger}', [PollingTriggerController::class, 'show'])->name('show');
                    Route::put('{pollingTrigger}', [PollingTriggerController::class, 'update'])->name('update');
                    Route::delete('{pollingTrigger}', [PollingTriggerController::class, 'destroy'])->name('destroy');
                });

                // ── Variables ────────────────────────────────────────

                Route::prefix('variables')->as('variables.')->group(function () {
                    Route::get('/', [VariableController::class, 'index'])->name('index');
                    Route::post('/', [VariableController::class, 'store'])->name('store');
                    Route::get('{variable}', [VariableController::class, 'show'])->name('show');
                    Route::put('{variable}', [VariableController::class, 'update'])->name('update');
                    Route::delete('{variable}', [VariableController::class, 'destroy'])->name('destroy');
                });

                // ── Tags ─────────────────────────────────────────────

                Route::prefix('tags')->as('tags.')->group(function () {
                    Route::get('/', [TagController::class, 'index'])->name('index');
                    Route::post('/', [TagController::class, 'store'])->name('store');
                    Route::get('{tag}', [TagController::class, 'show'])->name('show');
                    Route::put('{tag}', [TagController::class, 'update'])->name('update');
                    Route::delete('{tag}', [TagController::class, 'destroy'])->name('destroy');
                    Route::post('{tag}/workflows', [TagController::class, 'attachWorkflows'])->name('workflows.attach');
                    Route::delete('{tag}/workflows', [TagController::class, 'detachWorkflows'])->name('workflows.detach');
                });

                // ── Activity Logs ────────────────────────────────────

                Route::prefix('activity-logs')->as('activity-logs.')->group(function () {
                    Route::get('/', [ActivityLogController::class, 'index'])->name('index');
                    Route::get('export', [ActivityLogController::class, 'export'])->name('export');
                    Route::get('{activityLog}', [ActivityLogController::class, 'show'])->name('show');
                });

                // ── Credits & Billing ───────────────────────────────
                Route::prefix('credits')->as('credits.')->group(function () {
                    Route::get('balance', [CreditController::class, 'balance'])->name('balance');
                    Route::get('transactions', [CreditController::class, 'transactions'])->name('transactions');
                });

                Route::prefix('billing')->as('billing.')->group(function () {
                    Route::post('checkout', [BillingController::class, 'checkout'])->name('checkout');
                    Route::post('credits', [BillingController::class, 'buyCredits'])->name('buyCredits');
                    Route::get('portal', [BillingController::class, 'portal'])->name('portal');
                });

                // ── Workspace Settings ──────────────────────────────

                Route::prefix('settings')->as('settings.')->group(function () {
                    Route::get('/', [WorkspaceSettingController::class, 'show'])->name('show');
                    Route::put('/', [WorkspaceSettingController::class, 'update'])->name('update');
                });

                // ── OAuth2 Credential Flow ──────────────────────────

                Route::post('oauth/initiate', [OAuthCredentialController::class, 'initiate'])->name('oauth.initiate');

                // ── Log Streaming ───────────────────────────────────

                Route::prefix('log-streaming')->as('log-streaming.')->group(function () {
                    Route::get('/', [LogStreamingConfigController::class, 'index'])->name('index');
                    Route::post('/', [LogStreamingConfigController::class, 'store'])->name('store');
                    Route::get('{logStreamingConfig}', [LogStreamingConfigController::class, 'show'])->name('show');
                    Route::put('{logStreamingConfig}', [LogStreamingConfigController::class, 'update'])->name('update');
                    Route::delete('{logStreamingConfig}', [LogStreamingConfigController::class, 'destroy'])->name('destroy');
                });

                // ── Git Sync ────────────────────────────────────────

                Route::prefix('git-sync')->as('git-sync.')->group(function () {
                    Route::get('status', [GitSyncController::class, 'status'])->name('status');
                    Route::post('export', [GitSyncController::class, 'export'])->name('export');
                    Route::post('import', [GitSyncController::class, 'import'])->name('import');
                });

                // ── Shared Workflow Clone ────────────────────────────

                Route::post('shared/{token}/clone', [WorkflowShareController::class, 'clonePublic'])->name('shared.clone');

                // ── Templates (use within workspace) ────────────────

                Route::post('templates/{workflowTemplate}/use', [WorkflowTemplateController::class, 'use'])->name('templates.use');

                // ── Workflow Approvals ───────────────────────────────

                Route::prefix('approvals')->as('approvals.')->group(function () {
                    Route::get('/', [WorkflowApprovalController::class, 'index'])->name('index');
                    Route::get('{approval}', [WorkflowApprovalController::class, 'show'])->name('show');
                    Route::post('{approval}/approve', [WorkflowApprovalController::class, 'approve'])->name('approve');
                    Route::post('{approval}/reject', [WorkflowApprovalController::class, 'reject'])->name('reject');
                });

                // ── Environments ─────────────────────────────────────

                Route::prefix('environments')->as('environments.')->group(function () {
                    Route::get('/', [WorkspaceEnvironmentController::class, 'index'])->name('index');
                    Route::post('/', [WorkspaceEnvironmentController::class, 'store'])->name('store');
                    Route::get('{environment}', [WorkspaceEnvironmentController::class, 'show'])->name('show');
                    Route::put('{environment}', [WorkspaceEnvironmentController::class, 'update'])->name('update');
                    Route::delete('{environment}', [WorkspaceEnvironmentController::class, 'destroy'])->name('destroy');
                });

                // ── Connector Metrics ────────────────────────────────

                Route::prefix('connector-metrics')->as('connector-metrics.')->group(function () {
                    Route::get('/', [ConnectorMetricController::class, 'index'])->name('index');
                    Route::get('summary', [ConnectorMetricController::class, 'summary'])->name('summary');
                    Route::get('calls', [ConnectorMetricController::class, 'calls'])->name('calls');
                });

                // ── Node Sandbox ─────────────────────────────────────

                Route::post('nodes/sandbox', [NodeSandboxController::class, 'execute'])->name('nodes.sandbox');

                // ── Agents ──────────────────────────────────────────

                Route::prefix('agents')->as('agents.')->group(function () {
                    Route::get('/', [AgentController::class, 'index'])->name('index');
                    Route::post('/', [AgentController::class, 'store'])->name('store');

                    Route::prefix('{agent}')->group(function () {
                        Route::get('/', [AgentController::class, 'show'])->name('show');
                        Route::put('/', [AgentController::class, 'update'])->name('update');
                        Route::delete('/', [AgentController::class, 'destroy'])->name('destroy');
                        Route::post('duplicate', [AgentController::class, 'duplicate'])->name('duplicate');
                        Route::post('skills/attach', [AgentController::class, 'attachSkill'])->name('skills.attach');
                        Route::delete('skills/{skillId}', [AgentController::class, 'detachSkill'])->name('skills.detach');

                        // ── Conversations ─────────────────────────────
                        Route::prefix('conversations')->as('conversations.')->group(function () {
                            Route::get('/', [AgentConversationController::class, 'index'])->name('index');
                            Route::post('/', [AgentConversationController::class, 'store'])->name('store');
                            Route::get('{conversationId}', [AgentConversationController::class, 'show'])->name('show');
                            Route::delete('{conversationId}', [AgentConversationController::class, 'destroy'])->name('destroy');
                            Route::post('{conversationId}/messages', [AgentConversationController::class, 'sendMessage'])->name('messages.store');
                        });

                        // ── Triggers ──────────────────────────────────
                        Route::prefix('triggers')->as('triggers.')->group(function () {
                            Route::get('/', [AgentTriggerController::class, 'index'])->name('index');
                            Route::post('/', [AgentTriggerController::class, 'store'])->name('store');
                            Route::put('{trigger}', [AgentTriggerController::class, 'update'])->name('update');
                            Route::delete('{trigger}', [AgentTriggerController::class, 'destroy'])->name('destroy');
                            Route::post('{trigger}/fire', [AgentTriggerController::class, 'fire'])->name('fire');
                        });
                    });
                });

                // ── Agent Skills ─────────────────────────────────────

                Route::prefix('agent-skills')->as('agent-skills.')->group(function () {
                    Route::get('/', [AgentSkillController::class, 'index'])->name('index');
                    Route::post('/', [AgentSkillController::class, 'store'])->name('store');

                    Route::prefix('{agentSkill}')->group(function () {
                        Route::get('/', [AgentSkillController::class, 'show'])->name('show');
                        Route::put('/', [AgentSkillController::class, 'update'])->name('update');
                        Route::delete('/', [AgentSkillController::class, 'destroy'])->name('destroy');

                        // ── References ────────────────────────────────
                        Route::prefix('references')->as('references.')->group(function () {
                            Route::post('/', [AgentSkillController::class, 'addReference'])->name('store');
                            Route::put('{reference}', [AgentSkillController::class, 'updateReference'])->name('update');
                            Route::delete('{reference}', [AgentSkillController::class, 'removeReference'])->name('destroy');
                        });

                        // ── Scripts ───────────────────────────────────
                        Route::prefix('scripts')->as('scripts.')->group(function () {
                            Route::post('/', [AgentSkillController::class, 'addScript'])->name('store');
                            Route::put('{script}', [AgentSkillController::class, 'updateScript'])->name('update');
                            Route::delete('{script}', [AgentSkillController::class, 'removeScript'])->name('destroy');
                        });
                    });
                });
            });

        /*
        |------------------------------------------------------------------
        | Global Catalogs — Authenticated but not workspace-scoped
        |------------------------------------------------------------------
        |
        | These are read-only catalogs that any authenticated user can
        | browse. They don't belong to any workspace.
        |
        */

        // ── Node Types ───────────────────────────────────────────────

        Route::prefix('nodes')->as('nodes.')->group(function () {
            Route::get('/', [NodeController::class, 'index'])->name('index');
            Route::get('{node}', [NodeController::class, 'show'])->name('show');
        });

        Route::prefix('node-categories')->as('node-categories.')->group(function () {
            Route::get('/', [NodeCategoryController::class, 'index'])->name('index');
            Route::get('{nodeCategory}', [NodeCategoryController::class, 'show'])->name('show');
        });

        // ── Credential Types ─────────────────────────────────────────

        Route::prefix('credential-types')->as('credential-types.')->group(function () {
            Route::get('/', [CredentialTypeController::class, 'index'])->name('index');
            Route::get('{credentialType}', [CredentialTypeController::class, 'show'])->name('show');
        });

        // ── Template Admin (create / update / delete) ────────────────

        Route::prefix('templates')->as('templates.admin.')->group(function () {
            Route::post('/', [WorkflowTemplateController::class, 'store'])->name('store');
            Route::put('{workflowTemplate}', [WorkflowTemplateController::class, 'update'])->name('update');
            Route::delete('{workflowTemplate}', [WorkflowTemplateController::class, 'destroy'])->name('destroy');
        });
    });
});
