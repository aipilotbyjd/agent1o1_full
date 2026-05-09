<?php

namespace App\Listeners;

use App\Services\AdminAlertService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Fires an admin alert when a new user registers on the platform.
 */
class SendNewSignupAdminAlert implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 2;

    public function __construct(private readonly AdminAlertService $adminAlertService) {}

    public function handle(Registered $event): void
    {
        $this->adminAlertService->newSignup($event->user);
    }
}
