<?php

namespace App\Engine\Nodes\Apps\Postgresql;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;

/**
 * PostgreSQL node — executes SQL against a remote PostgreSQL instance via PDO.
 *
 * Credentials:
 *   host, port (5432), database, username, password
 *   sslmode — disable | allow | prefer | require (default: prefer)
 */
class PostgresqlNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'POSTGRESQL_ERROR';
    }

    protected function operations(): array
    {
        return [
            'execute_query' => $this->executeQuery(...),
            'insert' => $this->insert(...),
            'update' => $this->update(...),
            'delete' => $this->delete(...),
            'upsert' => $this->upsert(...),
        ];
    }

    private function connect(NodePayload $payload): \PDO
    {
        $host = (string) ($payload->credentials['host'] ?? '127.0.0.1');
        $port = (int) ($payload->credentials['port'] ?? 5432);
        $database = (string) ($payload->credentials['database'] ?? 'postgres');
        $username = (string) ($payload->credentials['username'] ?? 'postgres');
        $password = (string) ($payload->credentials['password'] ?? '');
        $sslmode = (string) ($payload->credentials['sslmode'] ?? 'prefer');

        $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslmode}";

        return new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_TIMEOUT => 10,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function executeQuery(NodePayload $payload): array
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
    private function insert(NodePayload $payload): array
    {
        $table = (string) ($payload->inputData['table'] ?? $payload->config['table'] ?? '');
        $data = (array) ($payload->inputData['data'] ?? $payload->config['data'] ?? []);
        $returning = (string) ($payload->config['returning'] ?? 'id');

        if (empty($data)) {
            throw new \InvalidArgumentException('No data provided for insert');
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn ($i) => '$'.($i + 1), range(0, count($columns) - 1));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING %s',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            $returning,
        );

        $pdo = $this->connect($payload);
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));

        $result = $stmt->fetchAll();

        return ['inserted' => true, 'returning' => $result, 'affected_rows' => $stmt->rowCount()];
    }

    /**
     * @return array<string, mixed>
     */
    private function update(NodePayload $payload): array
    {
        $table = (string) ($payload->inputData['table'] ?? $payload->config['table'] ?? '');
        $data = (array) ($payload->inputData['data'] ?? $payload->config['data'] ?? []);
        $where = (string) ($payload->inputData['where'] ?? $payload->config['where'] ?? '');
        $whereParams = (array) ($payload->inputData['where_params'] ?? $payload->config['where_params'] ?? []);

        if (empty($data) || empty($where)) {
            throw new \InvalidArgumentException('data and where clause are required for update');
        }

        $index = 1;
        $setParts = [];
        $setValues = [];

        foreach ($data as $col => $val) {
            $setParts[] = "{$col} = \${$index}";
            $setValues[] = $val;
            $index++;
        }

        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $setParts), $where);

        $pdo = $this->connect($payload);
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($setValues, $whereParams));

        return ['updated' => true, 'affected_rows' => $stmt->rowCount()];
    }

    /**
     * @return array<string, mixed>
     */
    private function delete(NodePayload $payload): array
    {
        $table = (string) ($payload->inputData['table'] ?? $payload->config['table'] ?? '');
        $where = (string) ($payload->inputData['where'] ?? $payload->config['where'] ?? '');
        $whereParams = (array) ($payload->inputData['where_params'] ?? $payload->config['where_params'] ?? []);

        if (empty($where)) {
            throw new \InvalidArgumentException('WHERE clause is required for delete');
        }

        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);

        $pdo = $this->connect($payload);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($whereParams);

        return ['deleted' => true, 'affected_rows' => $stmt->rowCount()];
    }

    /**
     * @return array<string, mixed>
     */
    private function upsert(NodePayload $payload): array
    {
        $table = (string) ($payload->inputData['table'] ?? $payload->config['table'] ?? '');
        $data = (array) ($payload->inputData['data'] ?? $payload->config['data'] ?? []);
        $conflictColumns = implode(', ', (array) ($payload->config['conflict_columns'] ?? ['id']));
        $returning = (string) ($payload->config['returning'] ?? 'id');

        if (empty($data)) {
            throw new \InvalidArgumentException('No data provided for upsert');
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn ($i) => '$'.($i + 1), range(0, count($columns) - 1));
        $updateParts = array_map(fn ($col) => "{$col} = EXCLUDED.{$col}", $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s RETURNING %s',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            $conflictColumns,
            implode(', ', $updateParts),
            $returning,
        );

        $pdo = $this->connect($payload);
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return ['upserted' => true, 'returning' => $stmt->fetchAll(), 'affected_rows' => $stmt->rowCount()];
    }
}
