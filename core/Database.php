<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * PDO singleton wrapper — always uses prepared statements.
 */
class Database
{
    private static ?self $instance = null;

    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $config;

    private function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function getInstance(array $config): self
    {
        if (self::$instance === null) {
            try {
                $pdo = self::createPdo($config);
                self::$instance = new self($config, $pdo);
            } catch (PDOException $e) {
                self::renderConnectionError($config, $e);
                exit;
            }
        }

        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Test connectivity without terminating the request (health check / installer).
     *
     * @param array<string, mixed> $config
     * @return array{connected: bool, error: string|null}
     */
    public static function testConnection(array $config): array
    {
        try {
            $pdo = self::createPdo($config);
            $result = $pdo->query('SELECT 1 AS ok');
            $row = $result !== false ? $result->fetch(PDO::FETCH_ASSOC) : false;
            $connected = is_array($row) && (int) ($row['ok'] ?? 0) === 1;

            return ['connected' => $connected, 'error' => $connected ? null : 'Unexpected query result'];
        } catch (PDOException $e) {
            return [
                'connected' => false,
                'error' => FORMFLOW_DEBUG ? $e->getMessage() : 'Connection failed',
            ];
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a prepared statement and return the statement.
     *
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare SQL statement.');
        }

        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Fetch a single row.
     *
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $row === false ? null : $row;
    }

    /**
     * Test database connectivity on an existing connection.
     */
    public function ping(): bool
    {
        $result = $this->fetchOne('SELECT 1 AS ok');

        return isset($result['ok']) && (int) $result['ok'] === 1;
    }

    public function table(string $name): string
    {
        return Db::table($name, $this->config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function createPdo(array $config): PDO
    {
        $db = $config['database'] ?? [];

        $host = (string) ($db['host'] ?? '127.0.0.1');
        $port = (int) ($db['port'] ?? 3306);
        $name = (string) ($db['name'] ?? '');
        $user = (string) ($db['user'] ?? '');
        $pass = (string) ($db['password'] ?? '');
        $charset = (string) ($db['charset'] ?? 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function renderConnectionError(array $config, PDOException $e): void
    {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');

        $viewFile = FORMFLOW_ROOT . '/views/errors/database.php';

        if (!is_readable($viewFile)) {
            echo '<!DOCTYPE html><html><head><title>Database Error</title></head><body>';
            echo '<h1>Database connection failed</h1>';
            if (FORMFLOW_DEBUG) {
                echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
            } else {
                echo '<p>Please try again later or contact the site administrator.</p>';
            }
            echo '</body></html>';
            return;
        }

        $exception = $e;
        require $viewFile;
    }
}
