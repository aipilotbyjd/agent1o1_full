<?php

namespace App\Engine\Nodes\Apps\Sendgrid;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;
use Illuminate\Support\Facades\Http;

class SendgridNode extends AppNode
{
    private const BASE_URL = 'https://api.sendgrid.com/v3';

    protected function errorCode(): string
    {
        return 'SENDGRID_ERROR';
    }

    protected function operations(): array
    {
        return [
            'send_email' => $this->sendEmail(...),
            'add_contact' => $this->addContact(...),
            'list_contacts' => $this->listContacts(...),
            'create_list' => $this->createList(...),
            'list_lists' => $this->listLists(...),
        ];
    }

    private function client(NodeInput $payload): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = (string) ($payload->credentials['api_key'] ?? $payload->credentials['access_token'] ?? '');

        return Http::withToken($apiKey)->baseUrl(self::BASE_URL);
    }

    /**
     * @return array<string, mixed>
     */
    private function sendEmail(NodeInput $payload): array
    {
        $to = $payload->inputData['to'] ?? $payload->config['to'] ?? '';
        $from = $payload->inputData['from'] ?? $payload->config['from'] ?? $payload->credentials['from_email'] ?? '';
        $subject = $payload->inputData['subject'] ?? $payload->config['subject'] ?? '';
        $body = $payload->inputData['body'] ?? $payload->config['body'] ?? '';
        $contentType = $payload->config['content_type'] ?? 'text/html';

        $toAddresses = is_array($to)
            ? array_map(fn ($e) => is_array($e) ? $e : ['email' => $e], $to)
            : [['email' => $to]];

        $response = $this->client($payload)->post('/mail/send', [
            'personalizations' => [['to' => $toAddresses, 'subject' => $subject]],
            'from' => is_array($from) ? $from : ['email' => $from],
            'content' => [['type' => $contentType, 'value' => $body]],
        ]);

        $response->throw();

        return ['sent' => true, 'status' => $response->status()];
    }

    /**
     * @return array<string, mixed>
     */
    private function addContact(NodeInput $payload): array
    {
        $contacts = [$payload->inputData['contact'] ?? array_filter([
            'email' => $payload->inputData['email'] ?? $payload->config['email'] ?? '',
            'first_name' => $payload->inputData['first_name'] ?? $payload->config['first_name'] ?? null,
            'last_name' => $payload->inputData['last_name'] ?? $payload->config['last_name'] ?? null,
        ])];

        $listIds = (array) ($payload->config['list_ids'] ?? []);

        $response = $this->client($payload)->put('/marketing/contacts', array_filter([
            'contacts' => $contacts,
            'list_ids' => $listIds ?: null,
        ]));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function listContacts(NodeInput $payload): array
    {
        $response = $this->client($payload)->get('/marketing/contacts', [
            'page_size' => $payload->config['limit'] ?? 25,
        ]);

        $response->throw();

        return ['contacts' => $response->json('result', [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function createList(NodeInput $payload): array
    {
        $name = (string) ($payload->inputData['name'] ?? $payload->config['name'] ?? '');

        $response = $this->client($payload)->post('/marketing/lists', ['name' => $name]);
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function listLists(NodeInput $payload): array
    {
        $response = $this->client($payload)->get('/marketing/lists', ['page_size' => $payload->config['limit'] ?? 20]);
        $response->throw();

        return ['lists' => $response->json('result', [])];
    }
}
