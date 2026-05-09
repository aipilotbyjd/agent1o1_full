<?php

namespace App\Engine\Nodes\Apps\Twitch;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use Illuminate\Support\Facades\Http;

class TwitchNode extends AppNode
{
    private const HELIX_URL = 'https://api.twitch.tv/helix';

    protected function errorCode(): string
    {
        return 'TWITCH_ERROR';
    }

    protected function operations(): array
    {
        return [
            'get_stream' => $this->getStream(...),
            'get_user' => $this->getUser(...),
            'get_channel' => $this->getChannel(...),
            'get_clips' => $this->getClips(...),
            'get_top_games' => $this->getTopGames(...),
            'create_clip' => $this->createClip(...),
            'send_chat_message' => $this->sendChatMessage(...),
        ];
    }

    private function client(NodePayload $payload): \Illuminate\Http\Client\PendingRequest
    {
        $token = (string) ($payload->credentials['access_token'] ?? '');
        $clientId = (string) ($payload->credentials['client_id'] ?? '');

        return Http::baseUrl(self::HELIX_URL)
            ->withToken($token)
            ->withHeader('Client-Id', $clientId);
    }

    /**
     * @return array<string, mixed>
     */
    private function getStream(NodePayload $payload): array
    {
        $userLogin = (string) ($payload->inputData['user_login'] ?? $payload->config['user_login'] ?? '');
        $userId = (string) ($payload->inputData['user_id'] ?? $payload->config['user_id'] ?? '');

        $params = array_filter(['user_login' => $userLogin ?: null, 'user_id' => $userId ?: null]);

        $response = $this->client($payload)->get('/streams', $params);
        $response->throw();

        $streams = $response->json('data', []);

        return ['stream' => $streams[0] ?? null, 'live' => ! empty($streams)];
    }

    /**
     * @return array<string, mixed>
     */
    private function getUser(NodePayload $payload): array
    {
        $login = (string) ($payload->inputData['login'] ?? $payload->config['login'] ?? '');
        $userId = (string) ($payload->inputData['user_id'] ?? $payload->config['user_id'] ?? '');

        $params = array_filter(['login' => $login ?: null, 'id' => $userId ?: null]);

        $response = $this->client($payload)->get('/users', $params);
        $response->throw();

        $users = $response->json('data', []);

        return ['user' => $users[0] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    private function getChannel(NodePayload $payload): array
    {
        $broadcasterId = (string) ($payload->inputData['broadcaster_id'] ?? $payload->config['broadcaster_id'] ?? '');

        $response = $this->client($payload)->get('/channels', ['broadcaster_id' => $broadcasterId]);
        $response->throw();

        $channels = $response->json('data', []);

        return ['channel' => $channels[0] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    private function getClips(NodePayload $payload): array
    {
        $params = array_filter([
            'broadcaster_id' => $payload->config['broadcaster_id'] ?? null,
            'game_id' => $payload->config['game_id'] ?? null,
            'first' => $payload->config['limit'] ?? 10,
        ]);

        $response = $this->client($payload)->get('/clips', $params);
        $response->throw();

        return ['clips' => $response->json('data', [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function getTopGames(NodePayload $payload): array
    {
        $response = $this->client($payload)->get('/games/top', ['first' => $payload->config['limit'] ?? 20]);
        $response->throw();

        return ['games' => $response->json('data', [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function createClip(NodePayload $payload): array
    {
        $broadcasterId = (string) ($payload->inputData['broadcaster_id'] ?? $payload->config['broadcaster_id'] ?? '');

        $response = $this->client($payload)->post('/clips', ['broadcaster_id' => $broadcasterId]);
        $response->throw();

        return ['clips' => $response->json('data', [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function sendChatMessage(NodePayload $payload): array
    {
        $broadcasterId = (string) ($payload->inputData['broadcaster_id'] ?? $payload->config['broadcaster_id'] ?? '');
        $senderId = (string) ($payload->credentials['user_id'] ?? $payload->config['sender_id'] ?? '');
        $message = (string) ($payload->inputData['message'] ?? $payload->config['message'] ?? '');

        $response = $this->client($payload)->post('/chat/messages', [
            'broadcaster_id' => $broadcasterId,
            'sender_id' => $senderId,
            'message' => $message,
        ]);

        $response->throw();

        return $response->json('data', []);
    }
}
