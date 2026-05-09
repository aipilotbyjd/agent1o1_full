<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Stripe\WebhookSignature;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('cashier.webhook.secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $secret
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            
            // Assuming client_reference_id is the workspace ID
            $workspaceId = $session->client_reference_id;
            if ($workspaceId) {
                $workspace = Workspace::find($workspaceId);
                if ($workspace) {
                    // Update stripe customer id
                    if (!$workspace->stripe_id && $session->customer) {
                        $workspace->stripe_id = $session->customer;
                        $workspace->save();
                    }
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}
