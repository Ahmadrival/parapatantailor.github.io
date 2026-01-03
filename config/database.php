<?php
// config/database.php
session_start();

class Database {
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'tailor_management';
    private $charset = 'utf8mb4';
    
    public $pdo;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }
    
    // Singleton pattern untuk mencegah multiple instances
    public static function getInstance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new Database();
        }
        return $instance;
    }
}

// Initialize database connection
$database = Database::getInstance();
$pdo = $database->pdo;

// Fungsi untuk membersihkan input (tambahan keamanan)
function clean_input($data) {
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Fungsi prepared statement untuk query
function executeQuery($sql, $params = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage());
        throw $e;
    }
}

// Fungsi untuk mendapatkan single row
function getSingle($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

// Fungsi untuk mendapatkan multiple rows
function getAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

// Redirect jika belum login
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Silakan login terlebih dahulu";
        header("Location: ../login.php");
        exit();
    }
}

// Cek role user
function check_role($allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        $_SESSION['error'] = "Anda tidak memiliki akses ke halaman tersebut";
        header("Location: ../dashboard.php");
        exit();
    }
}

// Fungsi untuk log activity (versi simplified)
function log_activity($action) {
    // Hanya log ke error log PHP tanpa database
    if (isset($_SESSION['user_id'])) {
        $log_message = sprintf(
            "[%s] User %s (%s): %s - IP: %s",
            date('Y-m-d H:i:s'),
            $_SESSION['user_id'],
            $_SESSION['username'] ?? 'Unknown',
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        );
        error_log($log_message);
    }
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Set timezone
date_default_timezone_set('Asia/Jakarta');
?>

