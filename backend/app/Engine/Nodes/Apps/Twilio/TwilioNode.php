<?php

namespace App\Engine\Nodes\Apps\Twilio;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;
use Illuminate\Support\Facades\Http;

class TwilioNode extends AppNode
{
    private const BASE_URL = 'https://api.twilio.com/2010-04-01';

    protected function errorCode(): string
    {
        return 'TWILIO_ERROR';
    }

    protected function operations(): array
    {
        return [
            'send_sms' => $this->sendSms(...),
            'send_whatsapp' => $this->sendWhatsapp(...),
            'make_call' => $this->makeCall(...),
            'list_messages' => $this->listMessages(...),
        ];
    }

    private function client(NodeInput $payload): \Illuminate\Http\Client\PendingRequest
    {
        $accountSid = (string) ($payload->credentials['account_sid'] ?? '');
        $authToken = (string) ($payload->credentials['auth_token'] ?? '');

        return Http::withBasicAuth($accountSid, $authToken)
            ->baseUrl(self::BASE_URL."/Accounts/{$accountSid}");
    }

    /**
     * @return array<string, mixed>
     */
    private function sendSms(NodeInput $payload): array
    {
        $accountSid = (string) ($payload->credentials['account_sid'] ?? '');
        $to = (string) ($payload->inputData['to'] ?? $payload->config['to'] ?? '');
        $from = (string) ($payload->inputData['from'] ?? $payload->config['from'] ?? $payload->credentials['from_number'] ?? '');
        $body = (string) ($payload->inputData['body'] ?? $payload->inputData['message'] ?? $payload->config['body'] ?? '');

        $response = $this->client($payload)->asForm()
            ->post("/Messages.json", ['To' => $to, 'From' => $from, 'Body' => $body]);

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function sendWhatsapp(NodeInput $payload): array
    {
        $to = 'whatsapp:'.(string) ($payload->inputData['to'] ?? $payload->config['to'] ?? '');
        $from = 'whatsapp:'.(string) ($payload->inputData['from'] ?? $payload->config['from'] ?? $payload->credentials['from_number'] ?? '');
        $body = (string) ($payload->inputData['body'] ?? $payload->config['body'] ?? '');

        $response = $this->client($payload)->asForm()
            ->post("/Messages.json", ['To' => $to, 'From' => $from, 'Body' => $body]);

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function makeCall(NodeInput $payload): array
    {
        $to = (string) ($payload->inputData['to'] ?? $payload->config['to'] ?? '');
        $from = (string) ($payload->config['from'] ?? $payload->credentials['from_number'] ?? '');
        $twiml = (string) ($payload->config['twiml'] ?? '<Response><Say>Hello from your workflow.</Say></Response>');

        $response = $this->client($payload)->asForm()
            ->post("/Calls.json", ['To' => $to, 'From' => $from, 'Twiml' => $twiml]);

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function listMessages(NodeInput $payload): array
    {
        $params = array_filter([
            'To' => $payload->config['to'] ?? null,
            'From' => $payload->config['from'] ?? null,
            'PageSize' => $payload->config['limit'] ?? 20,
        ]);

        $response = $this->client($payload)->get('/Messages.json', $params);
        $response->throw();

        return ['messages' => $response->json('messages', [])];
    }
}
