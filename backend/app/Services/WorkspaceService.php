<?php

namespace App\Services;

use App\Enums\BillingInterval;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Exceptions\ApiException;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class WorkspaceService
{
    /**
     * Create a new workspace for the given user.
     *
     * @param  array{name: string}  $data
     */
    public function create(User $owner, array $data): Workspace
    {
        $workspace = Workspace::query()->create([
            'name' => $data['name'],
            'slug' => $this->generateUniqueSlug($data['name']),
            'owner_id' => $owner->id,
        ]);

        // Assign owner to workspace_members
        $workspace->members()->attach($owner->id, [
            'id' => Str::uuid(),
            'role' => Role::Owner->value,
            'joined_at' => now(),
        ]);

        // Bootstrap billing state
        $this->bootstrapBilling($workspace);

        return $workspace;
    }

    /**
     * Bootstrap billing state for a new workspace.
     */
    private function bootstrapBilling(Workspace $workspace): void
    {
        // Look up the 'free' plan
        $freePlan = Plan::query()->where('slug', 'free')->firstOrFail();
        $monthlyCredits = $freePlan->getLimit('credits_monthly');

        // Create a Subscription row
        $subscription = $workspace->subscriptions()->create([
            'plan_id' => $freePlan->id,
            'status' => SubscriptionStatus::Active,
            'billing_interval' => BillingInterval::Monthly,
            'credits_monthly' => $monthlyCredits,
        ]);

        // Create a WorkspaceUsagePeriod row
        $workspace->usagePeriods()->create([
            'subscription_id' => $subscription->id,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addDays(30)->toDateString(),
            'credits_limit' => $monthlyCredits,
            'is_current' => true,
        ]);

        // Initialize Redis key for available credits
        try {
            Redis::set("credits:available:{$workspace->id}", $monthlyCredits);
        } catch (\Exception) {
            // Redis not available (e.g., in testing environment)
        }
    }

    /**
     * Update the given workspace.
     *
     * @param  array{name?: string}  $data
     */
    public function update(Workspace $workspace, array $data): Workspace
    {
        if (isset($data['name'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $workspace->id);
        }

        $workspace->update($data);

        return $workspace;
    }

    /**
     * Delete the given workspace.
     */
    public function delete(Workspace $workspace): void
    {
        $workspace->delete();
    }

    /**
     * Atomically transfer workspace ownership to a new owner.
     *
     * Rules enforced here (not just in the controller) so any code path is safe:
     *  - The new owner must currently be a member of the workspace
     *  - The new owner must currently hold the Admin role (or already be Owner)
     *  - The transfer is executed in a single DB transaction:
     *      1. workspace.owner_id = new owner
     *      2. old owner's pivot role → Admin
     *      3. new owner's pivot role → Owner
     *
     * @throws ApiException if the new owner is not an admin member
     */
    public function transferOwnership(Workspace $workspace, User $newOwner): void
    {
        $membership = $workspace->members()
            ->where('user_id', $newOwner->id)
            ->first();

        if (! $membership) {
            throw ApiException::unprocessable('The specified user is not a member of this workspace.');
        }

        $role = $membership->pivot->role ?? null;

        if (! in_array($role, [Role::Admin->value, Role::Owner->value], true)) {
            throw ApiException::unprocessable('Ownership can only be transferred to a workspace admin.');
        }

        if ($workspace->owner_id === $newOwner->id) {
            throw ApiException::unprocessable('The specified user is already the workspace owner.');
        }

        DB::transaction(function () use ($workspace, $newOwner) {
            $oldOwnerId = $workspace->owner_id;

            // 1. Change the canonical owner on the workspace row
            $workspace->update(['owner_id' => $newOwner->id]);

            // 2. Demote the old owner to Admin
            $workspace->members()->updateExistingPivot($oldOwnerId, [
                'role' => Role::Admin->value,
            ]);

            // 3. Promote the new owner
            $workspace->members()->updateExistingPivot($newOwner->id, [
                'role' => Role::Owner->value,
            ]);
        });
    }

    /**
     * Generate a unique slug from the given name.
     */
    private function generateUniqueSlug(string $name, ?string $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        $query = Workspace::query()->where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;

            $query = Workspace::query()->where('slug', $slug);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
