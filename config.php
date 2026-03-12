<?php
// ============================================
// DATABASE CONFIGURATION
// ============================================

define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql'); // mysql | pgsql
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'cafe_pos');
define('DB_SSLMODE', getenv('DB_SSLMODE') ?: 'require');

$envPort = getenv('DB_PORT');
define('DB_PORT', $envPort !== false ? $envPort : (DB_DRIVER === 'pgsql' ? '5432' : '3306'));

define('TAX_RATE', 0.12); // 12% VAT
define('CURRENCY', '₱');
define('CAFE_NAME', 'Brewed & Co.');
define('CAFE_ADDRESS', '123 Coffee Lane, Manila');
define('CAFE_PHONE', '+63 912 345 6789');

class DbResult {
    private $rows;
    private $index = 0;
    public $num_rows = 0;

    public function __construct($rows) {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc() {
        if ($this->index >= $this->num_rows) {
            return null;
        }
        return $this->rows[$this->index++];
    }
}

class DbCompat {
    private $pdo;
    public $error = '';
    public $insert_id = 0;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function set_charset($charset) {
        // No-op for PDO pgsql
    }

    public function real_escape_string($data) {
        $data = (string)$data;
        $search = ["\\", "\0", "\n", "\r", "'", '"', "\x1a"];
        $replace = ["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"];
        return str_replace($search, $replace, $data);
    }

    public function query($sql) {
        try {
            $stmt = $this->pdo->query($sql);
            if ($stmt === false) {
                $info = $this->pdo->errorInfo();
                $this->error = $info ? implode(' ', $info) : 'Query failed';
                return false;
            }
            if ($stmt->columnCount() > 0) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return new DbResult($rows);
            }
            $this->insert_id = (int)$this->pdo->lastInsertId();
            return true;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function begin_transaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollBack();
    }
}

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $usePgsql = (DB_DRIVER === 'pgsql');
        if (!$usePgsql && !class_exists('mysqli') && extension_loaded('pdo_pgsql')) {
            // Fallback to pgsql if mysqli is not available in the runtime
            $usePgsql = true;
        }
        if ($usePgsql) {
            $sslmode = DB_SSLMODE ? ';sslmode=' . DB_SSLMODE : '';
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . $sslmode;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $conn = new DbCompat($pdo);
        } else {
            if (!class_exists('mysqli')) {
                die(json_encode(['error' => 'mysqli extension not available and DB_DRIVER is not pgsql']));
            }
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            if ($conn->connect_error) {
                die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
            }
            $conn->set_charset('utf8mb4');
        }
    }
    return $conn;
}

function sanitize($data) {
    $db = getDB();
    return $db->real_escape_string(trim($data));
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function generateOrderNumber() {
    return 'ORD-' . strtoupper(date('ymd')) . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}
