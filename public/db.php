<?php
/**
 * DB — thin mysqli wrapper with prepared statements.
 * Mirrors the yuz DB class. Reads credentials from conf.php.
 */

// Return false on SQL errors (as the methods below expect) rather than
// throwing — a bad query degrades gracefully instead of a hard 500.
mysqli_report(MYSQLI_REPORT_OFF);

class DB {
    private $conn;

    public function __construct() {
        $configPath = realpath(__DIR__ . '/conf.php');

        if (!$configPath || !file_exists($configPath)) {
            throw new Exception("conf.php not found at: " . ($configPath ?: __DIR__ . '/conf.php'));
        }

        $config = require $configPath;

        $this->conn = new mysqli(
            $config['servername'],
            $config['username'],
            $config['password'],
            $config['dbname']
        );

        if ($this->conn->connect_error) {
            http_response_code(500);
            echo 'Internal server error.';
            exit;
        }

        $this->conn->set_charset('utf8mb4');
    }

    public function execute($query, $params = [], $types = ''): bool {
        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            return false;
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function select($query, $params = [], $types = ''): array|bool {
        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            return false;
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            return false;
        }
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function lastInsertId() {
        return $this->conn->insert_id;
    }

    public function getError(): string {
        return $this->conn->error;
    }

    public function close() {
        $this->conn->close();
    }
}
