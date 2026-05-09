<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use ApiResponse;

    /**
     * Create a Stripe Checkout Session for a plan subscription.
     */
    public function checkout(Request $request, Workspace $workspace): JsonResponse
    {
        $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        if (!$plan->stripe_price_id) {
            return $this->errorResponse('This plan is not available for online purchase.', 400);
        }

        try {
            $checkout = $workspace->newSubscription('default', $plan->stripe_price_id)
                ->checkout([
                    'success_url' => config('app.url') . "/workspaces/{$workspace->id}/billing?success=1",
                    'cancel_url' => config('app.url') . "/workspaces/{$workspace->id}/billing?cancel=1",
                ]);

            return $this->successResponse('Checkout session created', [
                'url' => $checkout->url,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create checkout session: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create a Stripe Checkout Session for a credit pack.
     */
    public function buyCredits(Request $request, Workspace $workspace): JsonResponse
    {
        $request->validate([
            'price_id' => ['required', 'string', 'starts_with:price_'],
        ]);
        
        try {
            $checkout = $workspace->checkout($request->price_id, [
                'success_url' => config('app.url') . "/workspaces/{$workspace->id}/billing?success=1",
                'cancel_url' => config('app.url') . "/workspaces/{$workspace->id}/billing?cancel=1",
            ]);
            
            return $this->successResponse('Checkout session created', [
                'url' => $checkout->url,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create checkout session: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get billing portal URL.
     */
    public function portal(Workspace $workspace): JsonResponse
    {
        try {
            $url = $workspace->billingPortalUrl(config('app.url') . "/workspaces/{$workspace->id}/billing");
            
            return $this->successResponse('Billing portal URL generated', [
                'url' => $url,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate billing portal URL: ' . $e->getMessage(), 500);
        }
    }
}
