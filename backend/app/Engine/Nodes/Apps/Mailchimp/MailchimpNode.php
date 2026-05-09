<?php

namespace App\Engine\Nodes\Apps\Mailchimp;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use Illuminate\Support\Facades\Http;

class MailchimpNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'MAILCHIMP_ERROR';
    }

    protected function operations(): array
    {
        return [
            'add_member' => $this->addMember(...),
            'update_member' => $this->updateMember(...),
            'get_member' => $this->getMember(...),
            'remove_member' => $this->removeMember(...),
            'list_lists' => $this->listLists(...),
            'create_campaign' => $this->createCampaign(...),
            'send_campaign' => $this->sendCampaign(...),
        ];
    }

    private function client(NodePayload $payload): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = (string) ($payload->credentials['api_key'] ?? $payload->credentials['access_token'] ?? '');
        $datacenter = explode('-', $apiKey)[1] ?? 'us1';

        return Http::withBasicAuth('anystring', $apiKey)
            ->baseUrl("https://{$datacenter}.api.mailchimp.com/3.0");
    }

    /**
     * @return array<string, mixed>
     */
    private function addMember(NodePayload $payload): array
    {
        $listId = (string) ($payload->inputData['list_id'] ?? $payload->config['list_id'] ?? '');
        $email = (string) ($payload->inputData['email'] ?? $payload->config['email'] ?? '');
        $status = (string) ($payload->config['status'] ?? 'subscribed');
        $mergeFields = $payload->inputData['merge_fields'] ?? $payload->config['merge_fields'] ?? [];

        $response = $this->client($payload)->post("/lists/{$listId}/members", array_filter([
            'email_address' => $email,
            'status' => $status,
            'merge_fields' => $mergeFields ?: null,
            'tags' => $payload->config['tags'] ?? null,
        ]));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function updateMember(NodePayload $payload): array
    {
        $listId = (string) ($payload->inputData['list_id'] ?? $payload->config['list_id'] ?? '');
        $email = strtolower(trim((string) ($payload->inputData['email'] ?? $payload->config['email'] ?? '')));
        $hash = md5($email);

        $response = $this->client($payload)->patch("/lists/{$listId}/members/{$hash}", array_filter([
            'status' => $payload->config['status'] ?? null,
            'merge_fields' => $payload->inputData['merge_fields'] ?? $payload->config['merge_fields'] ?? null,
        ]));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function getMember(NodePayload $payload): array
    {
        $listId = (string) ($payload->inputData['list_id'] ?? $payload->config['list_id'] ?? '');
        $email = strtolower(trim((string) ($payload->inputData['email'] ?? $payload->config['email'] ?? '')));
        $hash = md5($email);

        $response = $this->client($payload)->get("/lists/{$listId}/members/{$hash}");
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function removeMember(NodePayload $payload): array
    {
        $listId = (string) ($payload->inputData['list_id'] ?? $payload->config['list_id'] ?? '');
        $email = strtolower(trim((string) ($payload->inputData['email'] ?? $payload->config['email'] ?? '')));
        $hash = md5($email);

        $response = $this->client($payload)->delete("/lists/{$listId}/members/{$hash}");
        $response->throw();

        return ['removed' => true, 'email' => $email];
    }

    /**
     * @return array<string, mixed>
     */
    private function listLists(NodePayload $payload): array
    {
        $response = $this->client($payload)->get('/lists', ['count' => $payload->config['limit'] ?? 10]);
        $response->throw();

        return ['lists' => $response->json('lists', [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function createCampaign(NodePayload $payload): array
    {
        $response = $this->client($payload)->post('/campaigns', [
            'type' => $payload->config['type'] ?? 'regular',
            'recipients' => ['list_id' => $payload->config['list_id'] ?? ''],
            'settings' => array_filter([
                'subject_line' => $payload->inputData['subject'] ?? $payload->config['subject'] ?? '',
                'from_name' => $payload->config['from_name'] ?? '',
                'reply_to' => $payload->config['reply_to'] ?? '',
            ]),
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function sendCampaign(NodePayload $payload): array
    {
        $campaignId = (string) ($payload->inputData['campaign_id'] ?? $payload->config['campaign_id'] ?? '');

        $response = $this->client($payload)->post("/campaigns/{$campaignId}/actions/send");
        $response->throw();

        return ['sent' => true, 'campaign_id' => $campaignId];
    }
}
