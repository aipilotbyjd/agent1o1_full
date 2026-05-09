<?php

namespace App\Engine\Nodes\Apps\Trello;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use Illuminate\Support\Facades\Http;

class TrelloNode extends AppNode
{
    private const BASE_URL = 'https://api.trello.com/1';

    protected function errorCode(): string
    {
        return 'TRELLO_ERROR';
    }

    protected function operations(): array
    {
        return [
            'create_card' => $this->createCard(...),
            'update_card' => $this->updateCard(...),
            'get_card' => $this->getCard(...),
            'list_cards' => $this->listCards(...),
            'create_list' => $this->createList(...),
            'get_board' => $this->getBoard(...),
            'list_boards' => $this->listBoards(...),
        ];
    }

    private function auth(NodePayload $payload): array
    {
        return [
            'key' => (string) ($payload->credentials['api_key'] ?? ''),
            'token' => (string) ($payload->credentials['access_token'] ?? $payload->credentials['token'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createCard(NodePayload $payload): array
    {
        $response = Http::get(self::BASE_URL.'/cards', array_merge($this->auth($payload), array_filter([
            'idList' => $payload->inputData['list_id'] ?? $payload->config['list_id'] ?? '',
            'name' => $payload->inputData['name'] ?? $payload->config['name'] ?? '',
            'desc' => $payload->inputData['description'] ?? $payload->config['description'] ?? null,
            'due' => $payload->inputData['due'] ?? $payload->config['due'] ?? null,
            'pos' => $payload->config['position'] ?? 'bottom',
        ])));

        $response = Http::post(self::BASE_URL.'/cards', array_merge($this->auth($payload), array_filter([
            'idList' => $payload->inputData['list_id'] ?? $payload->config['list_id'] ?? '',
            'name' => $payload->inputData['name'] ?? $payload->config['name'] ?? '',
            'desc' => $payload->inputData['description'] ?? $payload->config['description'] ?? null,
            'due' => $payload->inputData['due'] ?? $payload->config['due'] ?? null,
            'pos' => $payload->config['position'] ?? 'bottom',
        ])));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function updateCard(NodePayload $payload): array
    {
        $cardId = (string) ($payload->inputData['card_id'] ?? $payload->config['card_id'] ?? '');

        $response = Http::put(self::BASE_URL."/cards/{$cardId}", array_merge($this->auth($payload), array_filter([
            'name' => $payload->inputData['name'] ?? $payload->config['name'] ?? null,
            'desc' => $payload->inputData['description'] ?? $payload->config['description'] ?? null,
            'due' => $payload->inputData['due'] ?? $payload->config['due'] ?? null,
            'closed' => $payload->config['closed'] ?? null,
        ])));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function getCard(NodePayload $payload): array
    {
        $cardId = (string) ($payload->inputData['card_id'] ?? $payload->config['card_id'] ?? '');

        $response = Http::get(self::BASE_URL."/cards/{$cardId}", $this->auth($payload));
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function listCards(NodePayload $payload): array
    {
        $listId = (string) ($payload->inputData['list_id'] ?? $payload->config['list_id'] ?? '');

        $response = Http::get(self::BASE_URL."/lists/{$listId}/cards", $this->auth($payload));
        $response->throw();

        return ['cards' => $response->json()];
    }

    /**
     * @return array<string, mixed>
     */
    private function createList(NodePayload $payload): array
    {
        $response = Http::post(self::BASE_URL.'/lists', array_merge($this->auth($payload), [
            'name' => $payload->inputData['name'] ?? $payload->config['name'] ?? 'New List',
            'idBoard' => $payload->inputData['board_id'] ?? $payload->config['board_id'] ?? '',
            'pos' => $payload->config['position'] ?? 'bottom',
        ]));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function getBoard(NodePayload $payload): array
    {
        $boardId = (string) ($payload->inputData['board_id'] ?? $payload->config['board_id'] ?? '');

        $response = Http::get(self::BASE_URL."/boards/{$boardId}", array_merge($this->auth($payload), [
            'lists' => 'open',
        ]));
        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function listBoards(NodePayload $payload): array
    {
        $response = Http::get(self::BASE_URL.'/members/me/boards', array_merge($this->auth($payload), [
            'filter' => 'open',
        ]));
        $response->throw();

        return ['boards' => $response->json()];
    }
}
