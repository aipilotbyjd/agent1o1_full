<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\ExecutionArchiveService;
use App\Services\PlanEnforcementService;
use Illuminate\Console\Command;

class ArchiveOldExecutionLogs extends Command
{
    protected $signature = 'executions:archive
                            {--workspace= : Specific workspace ID to archive}
                            {--dry-run : Preview without actually archiving}
                            {--batch-size=100 : Number of executions to archive per workspace}
                            {--force : Force archival even for active workspaces}';

    protected $description = 'Archive old execution logs to S3 based on plan retention limits';

    public function handle(
        ExecutionArchiveService $archiveService,
        PlanEnforcementService $planService
    ): int {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        $force = $this->option('force');

        $this->info('🗄️  Execution Log Archival');
        $this->info('Mode: '.($dryRun ? 'DRY RUN' : 'LIVE'));
        $this->newLine();

        // Get workspaces to process
        $workspaces = $this->getWorkspaces();

        if ($workspaces->isEmpty()) {
            $this->warn('No workspaces found to archive.');

            return self::SUCCESS;
        }

        $totalArchived = 0;
        $totalSpaceSaved = 0;
        $totalFailed = 0;

        foreach ($workspaces as $workspace) {
            $this->info("📦 Processing workspace: {$workspace->name} ({$workspace->id})");

            try {
                // Get plan retention limit
                $retentionDays = $planService->getLogRetentionDays($workspace);
                $cutoffDate = now()->subDays($retentionDays);

                $this->line("   Retention: {$retentionDays} days (cutoff: {$cutoffDate->toDateString()})");

                // Find old executions (only finished executions)
                $oldExecutions = $workspace->executions()
                    ->where('created_at', '<', $cutoffDate)
                    ->whereNotNull('finished_at') // Only archive completed executions
                    ->whereIn('status', ['completed', 'failed', 'cancelled'])
                    ->orderBy('created_at', 'asc')
                    ->limit($batchSize)
                    ->get();

                if ($oldExecutions->isEmpty()) {
                    $this->line('   ✓ No executions to archive');
                    $this->newLine();
                    continue;
                }

                $this->line("   Found {$oldExecutions->count()} executions to archive");

                if ($dryRun) {
                    foreach ($oldExecutions as $execution) {
                        $this->line("   [DRY RUN] Would archive: {$execution->id} (created: {$execution->created_at})");
                    }
                    $totalArchived += $oldExecutions->count();
                } else {
                    $progressBar = $this->output->createProgressBar($oldExecutions->count());
                    $progressBar->start();

                    foreach ($oldExecutions as $execution) {
                        try {
                            $archive = $archiveService->archiveExecution($execution);
                            $totalArchived++;
                            $totalSpaceSaved += $archive->file_size_bytes;
                            $progressBar->advance();
                        } catch (\Exception $e) {
                            $totalFailed++;
                            $this->newLine();
                            $this->error("   Failed to archive {$execution->id}: {$e->getMessage()}");
                            $progressBar->advance();
                        }
                    }

                    $progressBar->finish();
                    $this->newLine();
                }

                $this->line('   ✓ Completed');
                $this->newLine();
            } catch (\Exception $e) {
                $this->error("   ✗ Error processing workspace: {$e->getMessage()}");
                $this->newLine();
            }
        }

        // Summary
        $this->newLine();
        $this->info('✅ Archive Complete');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Mode', $dryRun ? 'DRY RUN' : 'LIVE'],
                ['Workspaces processed', $workspaces->count()],
                ['Executions archived', number_format($totalArchived)],
                ['Failed', number_format($totalFailed)],
                ['Space saved', $this->formatBytes($totalSpaceSaved)],
            ]
        );

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Get workspaces to process
     */
    private function getWorkspaces()
    {
        if ($workspaceId = $this->option('workspace')) {
            return Workspace::where('id', $workspaceId)->get();
        }

        $query = Workspace::query();

        if (! $this->option('force')) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * Format bytes to human-readable size
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
