<?php

namespace App\Engine\Nodes\Apps\AwsS3;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use Illuminate\Support\Facades\Http;

/**
 * AWS S3 node — uses the S3 REST API with AWS Signature Version 4.
 *
 * Works without the AWS SDK by implementing SigV4 signing directly.
 *
 * Credentials:
 *   access_key_id     — AWS access key ID
 *   secret_access_key — AWS secret access key
 *   region            — AWS region (e.g. us-east-1)
 *   bucket            — default S3 bucket name
 *   endpoint          — custom endpoint for S3-compatible storage (optional)
 */
class AwsS3Node extends AppNode
{
    protected function errorCode(): string
    {
        return 'AWS_S3_ERROR';
    }

    protected function operations(): array
    {
        return [
            'list_objects' => $this->listObjects(...),
            'get_object' => $this->getObject(...),
            'put_object' => $this->putObject(...),
            'delete_object' => $this->deleteObject(...),
            'copy_object' => $this->copyObject(...),
            'get_presigned_url' => $this->getPresignedUrl(...),
            'list_buckets' => $this->listBuckets(...),
        ];
    }

    private function endpoint(NodePayload $payload, ?string $bucket = null): string
    {
        $custom = rtrim((string) ($payload->credentials['endpoint'] ?? ''), '/');
        if ($custom) {
            return $bucket ? "{$custom}/{$bucket}" : $custom;
        }

        $region = (string) ($payload->credentials['region'] ?? 'us-east-1');
        $b = $bucket ?? (string) ($payload->credentials['bucket'] ?? '');

        return $b
            ? "https://{$b}.s3.{$region}.amazonaws.com"
            : "https://s3.{$region}.amazonaws.com";
    }

    private function sign(NodePayload $payload, string $method, string $url, string $body = '', array $extraHeaders = []): array
    {
        $accessKey = (string) ($payload->credentials['access_key_id'] ?? '');
        $secretKey = (string) ($payload->credentials['secret_access_key'] ?? '');
        $region = (string) ($payload->credentials['region'] ?? 'us-east-1');
        $service = 's3';

        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';
        $path = $parsedUrl['path'] ?? '/';
        $query = $parsedUrl['query'] ?? '';

        $now = new \DateTime('UTC');
        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');

        $payloadHash = hash('sha256', $body);

        $headers = array_merge([
            'host' => $host,
            'x-amz-date' => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
        ], array_change_key_case($extraHeaders, CASE_LOWER));

        ksort($headers);

        $canonicalHeaders = implode("\n", array_map(fn ($k, $v) => "{$k}:{$v}", array_keys($headers), $headers))."\n";
        $signedHeaders = implode(';', array_keys($headers));

        $canonicalRequest = implode("\n", [
            $method,
            $path,
            $query,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', $service,
                hash_hmac('sha256', $region,
                    hash_hmac('sha256', $dateStamp, "AWS4{$secretKey}", true),
                    true),
                true),
            true);

        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        return array_merge($headers, ['authorization' => $authorization, 'host' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listObjects(NodePayload $payload): array
    {
        $bucket = (string) ($payload->inputData['bucket'] ?? $payload->config['bucket'] ?? $payload->credentials['bucket'] ?? '');
        $prefix = (string) ($payload->config['prefix'] ?? '');
        $url = $this->endpoint($payload, $bucket).'/?list-type=2'.($prefix ? '&prefix='.urlencode($prefix) : '');

        $headers = $this->sign($payload, 'GET', $url);
        $response = Http::withHeaders(array_filter($headers))->get($url);
        $response->throw();

        $xml = simplexml_load_string($response->body());
        $objects = [];

        if ($xml && isset($xml->Contents)) {
            foreach ($xml->Contents as $obj) {
                $objects[] = [
                    'key' => (string) $obj->Key,
                    'size' => (int) $obj->Size,
                    'last_modified' => (string) $obj->LastModified,
                    'etag' => trim((string) $obj->ETag, '"'),
                ];
            }
        }

        return ['objects' => $objects, 'count' => count($objects)];
    }

    /**
     * @return array<string, mixed>
     */
    private function getObject(NodePayload $payload): array
    {
        $bucket = (string) ($payload->inputData['bucket'] ?? $payload->config['bucket'] ?? $payload->credentials['bucket'] ?? '');
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $url = $this->endpoint($payload, $bucket).'/'.ltrim($key, '/');

        $headers = $this->sign($payload, 'GET', $url);
        $response = Http::withHeaders(array_filter($headers))->get($url);
        $response->throw();

        $asText = (bool) ($payload->config['as_text'] ?? true);

        return [
            'key' => $key,
            'content' => $asText ? $response->body() : base64_encode($response->body()),
            'content_type' => $response->header('Content-Type'),
            'content_length' => $response->header('Content-Length'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function putObject(NodePayload $payload): array
    {
        $bucket = (string) ($payload->inputData['bucket'] ?? $payload->config['bucket'] ?? $payload->credentials['bucket'] ?? '');
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $content = (string) ($payload->inputData['content'] ?? $payload->config['content'] ?? '');
        $contentType = (string) ($payload->config['content_type'] ?? 'application/octet-stream');
        $url = $this->endpoint($payload, $bucket).'/'.ltrim($key, '/');

        $headers = $this->sign($payload, 'PUT', $url, $content, ['content-type' => $contentType]);
        $response = Http::withHeaders(array_filter($headers))
            ->withBody($content, $contentType)
            ->put($url);

        $response->throw();

        return ['uploaded' => true, 'bucket' => $bucket, 'key' => $key, 'etag' => trim($response->header('ETag'), '"')];
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteObject(NodePayload $payload): array
    {
        $bucket = (string) ($payload->inputData['bucket'] ?? $payload->config['bucket'] ?? $payload->credentials['bucket'] ?? '');
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $url = $this->endpoint($payload, $bucket).'/'.ltrim($key, '/');

        $headers = $this->sign($payload, 'DELETE', $url);
        $response = Http::withHeaders(array_filter($headers))->delete($url);
        $response->throw();

        return ['deleted' => true, 'bucket' => $bucket, 'key' => $key];
    }

    /**
     * @return array<string, mixed>
     */
    private function copyObject(NodePayload $payload): array
    {
        $bucket = (string) ($payload->inputData['bucket'] ?? $payload->config['bucket'] ?? $payload->credentials['bucket'] ?? '');
        $sourceKey = (string) ($payload->inputData['source_key'] ?? $payload->config['source_key'] ?? '');
        $destKey = (string) ($payload->inputData['dest_key'] ?? $payload->config['dest_key'] ?? '');
        $sourceBucket = (string) ($payload->config['source_bucket'] ?? $bucket);
        $copySource = "/{$sourceBucket}/".ltrim($sourceKey, '/');

        $url = $this->endpoint($payload, $bucket).'/'.ltrim($destKey, '/');
        $headers = $this->sign($payload, 'PUT', $url, '', ['x-amz-copy-source' => $copySource]);

        $response = Http::withHeaders(array_filter($headers))
            ->withHeader('x-amz-copy-source', $copySource)
            ->put($url);

        $response->throw();

        return ['copied' => true, 'source' => $copySource, 'destination' => $destKey];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPresignedUrl(NodePayload $payload): array
    {
        $bucket = (string) ($payload->inputData['bucket'] ?? $payload->config['bucket'] ?? $payload->credentials['bucket'] ?? '');
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $expiry = (int) ($payload->config['expiry'] ?? 3600);
        $method = strtoupper((string) ($payload->config['method'] ?? 'GET'));

        $accessKey = (string) ($payload->credentials['access_key_id'] ?? '');
        $secretKey = (string) ($payload->credentials['secret_access_key'] ?? '');
        $region = (string) ($payload->credentials['region'] ?? 'us-east-1');

        $now = new \DateTime('UTC');
        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');

        $host = "{$bucket}.s3.{$region}.amazonaws.com";
        $path = '/'.ltrim($key, '/');
        $credentialScope = "{$dateStamp}/{$region}/s3/aws4_request";

        $queryParams = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => "{$accessKey}/{$credentialScope}",
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) $expiry,
            'X-Amz-SignedHeaders' => 'host',
        ];

        ksort($queryParams);
        $query = http_build_query($queryParams);

        $canonicalRequest = implode("\n", [$method, $path, $query, "host:{$host}\n", 'host', 'UNSIGNED-PAYLOAD']);
        $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $credentialScope, hash('sha256', $canonicalRequest)]);

        $signingKey = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', 's3',
                hash_hmac('sha256', $region,
                    hash_hmac('sha256', $dateStamp, "AWS4{$secretKey}", true),
                    true),
                true),
            true);

        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $presignedUrl = "https://{$host}{$path}?{$query}&X-Amz-Signature={$signature}";

        return ['url' => $presignedUrl, 'expires_in' => $expiry, 'method' => $method];
    }

    /**
     * @return array<string, mixed>
     */
    private function listBuckets(NodePayload $payload): array
    {
        $url = 'https://s3.amazonaws.com/';
        $headers = $this->sign($payload, 'GET', $url);

        $response = Http::withHeaders(array_filter($headers))->get($url);
        $response->throw();

        $xml = simplexml_load_string($response->body());
        $buckets = [];

        if ($xml && isset($xml->Buckets->Bucket)) {
            foreach ($xml->Buckets->Bucket as $bucket) {
                $buckets[] = [
                    'name' => (string) $bucket->Name,
                    'creation_date' => (string) $bucket->CreationDate,
                ];
            }
        }

        return ['buckets' => $buckets];
    }
}
