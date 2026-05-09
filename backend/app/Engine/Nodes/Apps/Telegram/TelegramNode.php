<?php

namespace App\Engine\Nodes\Apps\Telegram;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;

class TelegramNode extends AppNode
{
    private const BASE_URL = 'https://api.telegram.org';

    protected function errorCode(): string
    {
        return 'TELEGRAM_ERROR';
    }

    protected function operations(): array
    {
        return [
            'send_message' => $this->sendMessage(...),
            'send_photo' => $this->sendPhoto(...),
            'send_document' => $this->sendDocument(...),
            'get_updates' => $this->getUpdates(...),
            'get_chat' => $this->getChat(...),
        ];
    }

    private function botToken(?array $credentials): string
    {
        return (string) ($credentials['bot_token'] ?? $credentials['access_token'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function sendMessage(NodePayload $payload): array
    {
        $token = $this->botToken($payload->credentials);
        $chatId = $payload->inputData['chat_id'] ?? $payload->config['chat_id'] ?? '';
        $text = $payload->inputData['text'] ?? $payload->inputData['message'] ?? $payload->config['text'] ?? '';
        $parseMode = $payload->config['parse_mode'] ?? 'HTML';

        $response = \Illuminate\Support\Facades\Http::post(
            self::BASE_URL."/bot{$token}/sendMessage",
            array_filter([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_notification' => $payload->config['silent'] ?? false,
                'reply_to_message_id' => $payload->config['reply_to'] ?? null,
            ]),
        );

        $response->throw();

        return $response->json('result', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function sendPhoto(NodePayload $payload): array
    {
        $token = $this->botToken($payload->credentials);
        $chatId = $payload->inputData['chat_id'] ?? $payload->config['chat_id'] ?? '';
        $photo = $payload->inputData['photo'] ?? $payload->config['photo'] ?? '';
        $caption = $payload->inputData['caption'] ?? $payload->config['caption'] ?? null;

        $response = \Illuminate\Support\Facades\Http::post(
            self::BASE_URL."/bot{$token}/sendPhoto",
            array_filter(['chat_id' => $chatId, 'photo' => $photo, 'caption' => $caption]),
        );

        $response->throw();

        return $response->json('result', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function sendDocument(NodePayload $payload): array
    {
        $token = $this->botToken($payload->credentials);
        $chatId = $payload->inputData['chat_id'] ?? $payload->config['chat_id'] ?? '';
        $document = $payload->inputData['document'] ?? $payload->config['document'] ?? '';
        $caption = $payload->inputData['caption'] ?? $payload->config['caption'] ?? null;

        $response = \Illuminate\Support\Facades\Http::post(
            self::BASE_URL."/bot{$token}/sendDocument",
            array_filter(['chat_id' => $chatId, 'document' => $document, 'caption' => $caption]),
        );

        $response->throw();

        return $response->json('result', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function getUpdates(NodePayload $payload): array
    {
        $token = $this->botToken($payload->credentials);
        $offset = $payload->config['offset'] ?? null;
        $limit = $payload->config['limit'] ?? 100;

        $response = \Illuminate\Support\Facades\Http::get(
            self::BASE_URL."/bot{$token}/getUpdates",
            array_filter(['offset' => $offset, 'limit' => $limit]),
        );

        $response->throw();

        return ['updates' => $response->json('result', [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function getChat(NodePayload $payload): array
    {
        $token = $this->botToken($payload->credentials);
        $chatId = $payload->inputData['chat_id'] ?? $payload->config['chat_id'] ?? '';

        $response = \Illuminate\Support\Facades\Http::get(
            self::BASE_URL."/bot{$token}/getChat",
            ['chat_id' => $chatId],
        );

        $response->throw();

        return $response->json('result', []);
    }
}
