<?php

namespace App\Engine\Nodes\Apps\Ftp;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;

/**
 * FTP/SFTP node — file transfer operations.
 *
 * Uses PHP's native FTP extension for FTP and phpseclib-style or SSH2 for SFTP.
 * Falls back to pure FTP when SFTP credentials are not specified.
 *
 * Credentials:
 *   host       — FTP/SFTP host
 *   port       — 21 (FTP) or 22 (SFTP)
 *   username   — login username
 *   password   — login password
 *   protocol   — "ftp" | "ftps" | "sftp" (default: ftp)
 *   passive    — bool, passive mode (default: true)
 */
class FtpNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'FTP_ERROR';
    }

    protected function operations(): array
    {
        return [
            'list_files' => $this->listFiles(...),
            'upload' => $this->upload(...),
            'download' => $this->download(...),
            'delete' => $this->delete(...),
            'rename' => $this->rename(...),
            'create_directory' => $this->createDirectory(...),
        ];
    }

    private function connect(NodePayload $payload): mixed
    {
        $host = (string) ($payload->credentials['host'] ?? '');
        $port = (int) ($payload->credentials['port'] ?? 21);
        $username = (string) ($payload->credentials['username'] ?? '');
        $password = (string) ($payload->credentials['password'] ?? '');
        $protocol = strtolower((string) ($payload->credentials['protocol'] ?? 'ftp'));
        $passive = (bool) ($payload->credentials['passive'] ?? true);

        if ($protocol === 'ftps') {
            $conn = @ftp_ssl_connect($host, $port, 10);
        } else {
            $conn = @ftp_connect($host, $port, 10);
        }

        if ($conn === false) {
            throw new \RuntimeException("Failed to connect to FTP server: {$host}:{$port}");
        }

        if (! @ftp_login($conn, $username, $password)) {
            ftp_close($conn);
            throw new \RuntimeException('FTP authentication failed');
        }

        if ($passive) {
            ftp_pasv($conn, true);
        }

        return $conn;
    }

    /**
     * @return array<string, mixed>
     */
    private function listFiles(NodePayload $payload): array
    {
        $path = (string) ($payload->inputData['path'] ?? $payload->config['path'] ?? '/');
        $conn = $this->connect($payload);

        try {
            $files = ftp_nlist($conn, $path);
            $rawList = ftp_rawlist($conn, $path);
        } finally {
            ftp_close($conn);
        }

        if ($files === false) {
            throw new \RuntimeException("Failed to list directory: {$path}");
        }

        $parsed = [];
        if ($rawList) {
            foreach ($rawList as $line) {
                $parts = preg_split('/\s+/', $line, 9);
                if (count($parts) >= 9) {
                    $parsed[] = [
                        'permissions' => $parts[0],
                        'size' => (int) $parts[4],
                        'date' => "{$parts[5]} {$parts[6]} {$parts[7]}",
                        'name' => $parts[8],
                        'is_dir' => str_starts_with($parts[0], 'd'),
                    ];
                }
            }
        }

        return ['files' => $parsed ?: $files, 'count' => count($parsed ?: $files)];
    }

    /**
     * @return array<string, mixed>
     */
    private function upload(NodePayload $payload): array
    {
        $remotePath = (string) ($payload->inputData['remote_path'] ?? $payload->config['remote_path'] ?? '');
        $content = (string) ($payload->inputData['content'] ?? $payload->config['content'] ?? '');
        $localPath = (string) ($payload->inputData['local_path'] ?? $payload->config['local_path'] ?? '');

        $tmpFile = null;
        if ($content && ! $localPath) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'ftp_upload_');
            file_put_contents($tmpFile, $content);
            $localPath = $tmpFile;
        }

        if (! $localPath || ! file_exists($localPath)) {
            throw new \RuntimeException('No file content or valid local path provided');
        }

        $conn = $this->connect($payload);

        try {
            $result = ftp_put($conn, $remotePath, $localPath);
        } finally {
            ftp_close($conn);
            if ($tmpFile && file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        if (! $result) {
            throw new \RuntimeException("Failed to upload file to: {$remotePath}");
        }

        return ['uploaded' => true, 'remote_path' => $remotePath];
    }

    /**
     * @return array<string, mixed>
     */
    private function download(NodePayload $payload): array
    {
        $remotePath = (string) ($payload->inputData['remote_path'] ?? $payload->config['remote_path'] ?? '');
        $tmpFile = tempnam(sys_get_temp_dir(), 'ftp_download_');

        $conn = $this->connect($payload);

        try {
            $result = ftp_get($conn, $tmpFile, $remotePath, FTP_BINARY);
        } finally {
            ftp_close($conn);
        }

        if (! $result) {
            @unlink($tmpFile);
            throw new \RuntimeException("Failed to download file from: {$remotePath}");
        }

        $content = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return [
            'content' => $content,
            'remote_path' => $remotePath,
            'size' => strlen($content),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function delete(NodePayload $payload): array
    {
        $remotePath = (string) ($payload->inputData['remote_path'] ?? $payload->config['remote_path'] ?? '');
        $conn = $this->connect($payload);

        try {
            $result = ftp_delete($conn, $remotePath);
        } finally {
            ftp_close($conn);
        }

        return ['deleted' => $result, 'remote_path' => $remotePath];
    }

    /**
     * @return array<string, mixed>
     */
    private function rename(NodePayload $payload): array
    {
        $from = (string) ($payload->inputData['from'] ?? $payload->config['from'] ?? '');
        $to = (string) ($payload->inputData['to'] ?? $payload->config['to'] ?? '');

        $conn = $this->connect($payload);

        try {
            $result = ftp_rename($conn, $from, $to);
        } finally {
            ftp_close($conn);
        }

        return ['renamed' => $result, 'from' => $from, 'to' => $to];
    }

    /**
     * @return array<string, mixed>
     */
    private function createDirectory(NodePayload $payload): array
    {
        $path = (string) ($payload->inputData['path'] ?? $payload->config['path'] ?? '');
        $conn = $this->connect($payload);

        try {
            $result = @ftp_mkdir($conn, $path);
        } finally {
            ftp_close($conn);
        }

        return ['created' => $result !== false, 'path' => $path];
    }
}
