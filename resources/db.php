<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'tahukocek');
define('DB_USER', 'root');
define('DB_PASS', '');         
define('DB_CHARSET', 'utf8mb4');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            // Jangan tampilkan detail error ke user di production
            die(json_encode([
                'success' => false,
                'message' => 'Koneksi database gagal. Hubungi administrator.',
                'debug'   => (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : null,
            ]));
        }
    }
    return $pdo;
}


function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireAdmin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Akses ditolak.'], 401);
    }
}


function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['logged_in'])) {
        jsonResponse(['success' => false, 'message' => 'Silakan login terlebih dahulu.'], 401);
    }
}
