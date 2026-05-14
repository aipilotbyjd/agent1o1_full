<?php

namespace App\Engine\Nodes\Apps\Mysql;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;

/**
 * MySQL node — executes SQL against a remote MySQL/MariaDB instance via PDO.
 *
 * Credentials:
 *   host, port (3306), database, username, password
 *   ssl (bool) — enable SSL/TLS
 */
class MysqlNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'MYSQL_ERROR';
    }

    protected function operations(): array
    {
        return [
            'execute_query' => $this->executeQuery(...),
            'insert' => $this->insert(...),
            'update' => $this->update(...),
            'delete' => $this->delete(...),
        ];
    }

    private function connect(NodeInput $payload): \PDO
    {
        $host = (string) ($payload->credentials['host'] ?? '127.0.0.1');
        $port = (int) ($payload->credentials['port'] ?? 3306);
        $database = (string) ($payload->credentials['database'] ?? '');
        $username = (string) ($payload->credentials['username'] ?? 'root');
        $password = (string) ($payload->credentials['password'] ?? '');
        $ssl = (bool) ($payload->credentials['ssl'] ?? false);

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_TIMEOUT => 10,
        ];

        if ($ssl) {
            $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        return new \PDO($dsn, $username, $password, $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function executeQuery(NodeInput $payload): array
    {
        $sql = (string) ($payload->inputData['query'] ?? $payload->config['query'] ?? '');
        $params = (array) ($payload->inputData['params'] ?? $payload->config['params'] ?? []);
        $limit = (int) ($payload->config['limit'] ?? 1000);

        $pdo = $this->connect($payload);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();

        if (count($rows) > $limit) {
            $rows = array_slice($rows, 0, $limit);
        }

        return [
            'rows' => $rows,
            'count' => count($rows),
            'affected_rows' => $stmt->rowCount(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function insert(NodeInput $payload): array
    {
        $table = (string) ($payload->inputData['table'] ?? $payload->config['table'] ?? '');
        $data = (array) ($payload->inputData['data'] ?? $payload->config['data'] ?? []);

        if (empty($data)) {
            throw new \InvalidArgumentException('No data provided for insert');
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn ($col) => ":{$col}", $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            str_replace('`', '', $table),
            implode(', ', array_map(fn ($c) => "`{$c}`", $columns)),
            implode(', ', $placeholders),
        );

        $pdo = $this->connect($payload);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return ['inserted' => true, 'last_insert_id' => $pdo->lastInsertId(), 'affected_rows' => $stmt->rowCount()];
    }

    /**
     * @return array<string, mixed>
     */
    private function update(NodeInput $payload): array
    {
        $table = (string) ($payload->inputData['table'] ?? $payload->config['table'] ?? '');
        $data = (array) ($payload->inputData['data'] ?? $payload->config['data'] ?? []);
        $where = (string) ($payload->inputData['where'] ?? $payload->config['where'] ?? '');
        $whereParams = (array) ($payload->inputData['where_params'] ?? $payload->config['where_params'] ?? []);

        if (empty($data) || empty($where)) {
            throw new \InvalidArgumentException('data and where clause are required for update');
        }

        $setParts = array_map(fn ($col) => "`{$col}` = :set_{$col}", array_keys($data));
        $namedData = array_combine(array_map(fn ($k) => "set_{$k}", array_keys($data)), array_values($data));

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', str_replace('`', '', $table), implode(', ', $setParts), $where);

        $pdo = $this->connect($payload);
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($namedData, $whereParams));

        return ['updated' => true, 'affected_rows' => $stmt->rowCount()];
    }

    /**
     * @return array<string, mixed>
     */
    private function delete(NodeInput $payload): array
    {
        $table = (string) ($payload->inputData['table'] ?? $payload->config['table'] ?? '');
        $where = (string) ($payload->inputData['where'] ?? $payload->config['where'] ?? '');
        $whereParams = (array) ($payload->inputData['where_params'] ?? $payload->config['where_params'] ?? []);

        if (empty($where)) {
            throw new \InvalidArgumentException('WHERE clause is required for delete to prevent accidental full-table delete');
        }

        $sql = sprintf('DELETE FROM `%s` WHERE %s', str_replace('`', '', $table), $where);

        $pdo = $this->connect($payload);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($whereParams);

        return ['deleted' => true, 'affected_rows' => $stmt->rowCount()];
    }
}
