<?php

namespace App\Engine\Nodes\Apps\Hubspot;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;

class HubspotNode extends AppNode
{
    private const BASE_URL = 'https://api.hubapi.com';

    protected function errorCode(): string
    {
        return 'HUBSPOT_ERROR';
    }

    protected function operations(): array
    {
        return [
            'create_contact' => $this->createContact(...),
            'update_contact' => $this->updateContact(...),
            'get_contact' => $this->getContact(...),
            'search_contacts' => $this->searchContacts(...),
            'create_deal' => $this->createDeal(...),
            'update_deal' => $this->updateDeal(...),
            'list_deals' => $this->listDeals(...),
            'create_company' => $this->createCompany(...),
        ];
    }

    private function client(NodeInput $payload): \Illuminate\Http\Client\PendingRequest
    {
        $token = (string) ($payload->credentials['access_token'] ?? $payload->credentials['api_key'] ?? '');

        return $this->authenticatedRequest($payload->credentials)
            ->baseUrl(self::BASE_URL)
            ->withHeader('Authorization', "Bearer {$token}");
    }

    /**
     * @return array<string, mixed>
     */
    private function createContact(NodeInput $payload): array
    {
        $properties = $payload->inputData['properties'] ?? array_filter([
            'email' => $payload->inputData['email'] ?? $payload->config['email'] ?? null,
            'firstname' => $payload->inputData['firstname'] ?? $payload->config['firstname'] ?? null,
            'lastname' => $payload->inputData['lastname'] ?? $payload->config['lastname'] ?? null,
            'phone' => $payload->inputData['phone'] ?? $payload->config['phone'] ?? null,
            'company' => $payload->inputData['company'] ?? $payload->config['company'] ?? null,
        ]);

        $response = $this->client($payload)->post('/crm/v3/objects/contacts', ['properties' => $properties]);
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function updateContact(NodeInput $payload): array
    {
        $contactId = (string) ($payload->inputData['contact_id'] ?? $payload->config['contact_id'] ?? '');
        $properties = $payload->inputData['properties'] ?? $payload->config['properties'] ?? [];

        $response = $this->client($payload)->patch("/crm/v3/objects/contacts/{$contactId}", ['properties' => $properties]);
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function getContact(NodeInput $payload): array
    {
        $contactId = (string) ($payload->inputData['contact_id'] ?? $payload->config['contact_id'] ?? '');

        $response = $this->client($payload)->get("/crm/v3/objects/contacts/{$contactId}");
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function searchContacts(NodeInput $payload): array
    {
        $query = (string) ($payload->inputData['query'] ?? $payload->config['query'] ?? '');

        $response = $this->client($payload)->post('/crm/v3/objects/contacts/search', [
            'query' => $query,
            'limit' => $payload->config['limit'] ?? 20,
            'properties' => $payload->config['properties'] ?? ['email', 'firstname', 'lastname', 'phone'],
        ]);

        $response->throw();

        return ['contacts' => $response->json('results', []), 'total' => $response->json('total', 0)];
    }

    /**
     * @return array<string, mixed>
     */
    private function createDeal(NodeInput $payload): array
    {
        $properties = $payload->inputData['properties'] ?? array_filter([
            'dealname' => $payload->inputData['dealname'] ?? $payload->config['dealname'] ?? null,
            'amount' => $payload->inputData['amount'] ?? $payload->config['amount'] ?? null,
            'closedate' => $payload->inputData['closedate'] ?? $payload->config['closedate'] ?? null,
            'dealstage' => $payload->config['dealstage'] ?? 'appointmentscheduled',
            'pipeline' => $payload->config['pipeline'] ?? 'default',
        ]);

        $response = $this->client($payload)->post('/crm/v3/objects/deals', ['properties' => $properties]);
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function updateDeal(NodeInput $payload): array
    {
        $dealId = (string) ($payload->inputData['deal_id'] ?? $payload->config['deal_id'] ?? '');
        $properties = $payload->inputData['properties'] ?? $payload->config['properties'] ?? [];

        $response = $this->client($payload)->patch("/crm/v3/objects/deals/{$dealId}", ['properties' => $properties]);
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function listDeals(NodeInput $payload): array
    {
        $response = $this->client($payload)->get('/crm/v3/objects/deals', [
            'limit' => $payload->config['limit'] ?? 20,
            'properties' => implode(',', $payload->config['properties'] ?? ['dealname', 'amount', 'dealstage', 'closedate']),
        ]);

        $response->throw();

        return ['deals' => $response->json('results', [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function createCompany(NodeInput $payload): array
    {
        $properties = $payload->inputData['properties'] ?? array_filter([
            'name' => $payload->inputData['name'] ?? $payload->config['name'] ?? null,
            'domain' => $payload->inputData['domain'] ?? $payload->config['domain'] ?? null,
            'industry' => $payload->config['industry'] ?? null,
            'phone' => $payload->inputData['phone'] ?? $payload->config['phone'] ?? null,
        ]);

        $response = $this->client($payload)->post('/crm/v3/objects/companies', ['properties' => $properties]);
        $response->throw();

        return $response->json();
    }
}
