<?php

namespace App\Engine\Nodes\Apps\Twitter;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;
use Illuminate\Support\Facades\Http;

/**
 * Twitter/X node — uses the Twitter API v2.
 *
 * Credentials:
 *   bearer_token  — app-only (read-only operations)
 *   access_token  — OAuth2 user access token (write operations)
 */
class TwitterNode extends AppNode
{
    private const BASE_URL = 'https://api.twitter.com/2';

    protected function errorCode(): string
    {
        return 'TWITTER_ERROR';
    }

    protected function operations(): array
    {
        return [
            'create_tweet' => $this->createTweet(...),
            'delete_tweet' => $this->deleteTweet(...),
            'get_tweet' => $this->getTweet(...),
            'search_tweets' => $this->searchTweets(...),
            'get_user' => $this->getUser(...),
            'get_user_tweets' => $this->getUserTweets(...),
            'like_tweet' => $this->likeTweet(...),
            'retweet' => $this->retweet(...),
        ];
    }

    private function client(NodeInput $payload): \Illuminate\Http\Client\PendingRequest
    {
        $token = (string) ($payload->credentials['access_token'] ?? $payload->credentials['bearer_token'] ?? '');

        return Http::withToken($token)->baseUrl(self::BASE_URL);
    }

    /**
     * @return array<string, mixed>
     */
    private function createTweet(NodeInput $payload): array
    {
        $text = (string) ($payload->inputData['text'] ?? $payload->inputData['tweet'] ?? $payload->config['text'] ?? '');

        $body = array_filter([
            'text' => $text,
            'reply' => isset($payload->config['reply_to_id']) ? ['in_reply_to_tweet_id' => $payload->config['reply_to_id']] : null,
        ]);

        $response = $this->client($payload)->post('/tweets', $body);
        $response->throw();

        return $response->json('data', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteTweet(NodeInput $payload): array
    {
        $tweetId = (string) ($payload->inputData['tweet_id'] ?? $payload->config['tweet_id'] ?? '');

        $response = $this->client($payload)->delete("/tweets/{$tweetId}");
        $response->throw();

        return ['deleted' => $response->json('data.deleted', false)];
    }

    /**
     * @return array<string, mixed>
     */
    private function getTweet(NodeInput $payload): array
    {
        $tweetId = (string) ($payload->inputData['tweet_id'] ?? $payload->config['tweet_id'] ?? '');

        $response = $this->client($payload)->get("/tweets/{$tweetId}", [
            'tweet.fields' => $payload->config['fields'] ?? 'created_at,author_id,public_metrics,text',
        ]);

        $response->throw();

        return $response->json('data', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function searchTweets(NodeInput $payload): array
    {
        $query = (string) ($payload->inputData['query'] ?? $payload->config['query'] ?? '');

        $response = $this->client($payload)->get('/tweets/search/recent', array_filter([
            'query' => $query,
            'max_results' => $payload->config['limit'] ?? 10,
            'tweet.fields' => $payload->config['fields'] ?? 'created_at,author_id,public_metrics',
        ]));

        $response->throw();

        return [
            'tweets' => $response->json('data', []),
            'meta' => $response->json('meta', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getUser(NodeInput $payload): array
    {
        $username = ltrim((string) ($payload->inputData['username'] ?? $payload->config['username'] ?? ''), '@');
        $userId = (string) ($payload->inputData['user_id'] ?? $payload->config['user_id'] ?? '');

        $endpoint = $userId ? "/users/{$userId}" : "/users/by/username/{$username}";

        $response = $this->client($payload)->get($endpoint, [
            'user.fields' => $payload->config['fields'] ?? 'created_at,description,public_metrics,profile_image_url',
        ]);

        $response->throw();

        return $response->json('data', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function getUserTweets(NodeInput $payload): array
    {
        $userId = (string) ($payload->inputData['user_id'] ?? $payload->config['user_id'] ?? '');

        $response = $this->client($payload)->get("/users/{$userId}/tweets", array_filter([
            'max_results' => $payload->config['limit'] ?? 10,
            'tweet.fields' => $payload->config['fields'] ?? 'created_at,public_metrics',
        ]));

        $response->throw();

        return ['tweets' => $response->json('data', [])];
    }

    /**
     * @return array<string, mixed>
     */
    private function likeTweet(NodeInput $payload): array
    {
        $userId = (string) ($payload->credentials['user_id'] ?? $payload->config['user_id'] ?? '');
        $tweetId = (string) ($payload->inputData['tweet_id'] ?? $payload->config['tweet_id'] ?? '');

        $response = $this->client($payload)->post("/users/{$userId}/likes", ['tweet_id' => $tweetId]);
        $response->throw();

        return $response->json('data', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function retweet(NodeInput $payload): array
    {
        $userId = (string) ($payload->credentials['user_id'] ?? $payload->config['user_id'] ?? '');
        $tweetId = (string) ($payload->inputData['tweet_id'] ?? $payload->config['tweet_id'] ?? '');

        $response = $this->client($payload)->post("/users/{$userId}/retweets", ['tweet_id' => $tweetId]);
        $response->throw();

        return $response->json('data', []);
    }
}
