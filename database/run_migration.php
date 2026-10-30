<?php
/**
 * run_migration.php – Jalankan semua migration otomatis
 * Akses SEKALI: http://localhost/TahuKocek/database/run_migration.php
 * Hapus file ini setelah selesai.
 */

require_once __DIR__ . '/../resources/db.php';

$migrations = [
    // Migration 1: tambah qris_url ke tabel toko
    'qris_url ke toko' => "ALTER TABLE toko ADD COLUMN IF NOT EXISTS qris_url VARCHAR(255) NOT NULL DEFAULT '' AFTER metode_qris",
];

echo "<pre style='font-family:monospace;padding:20px'>";
echo "=== TahuKocek Migration Runner ===\n\n";

$pdo = db();
$allOk = true;

foreach ($migrations as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ [{$name}] Berhasil\n";
    } catch (PDOException $e) {
        // Jika kolom sudah ada, MySQL melempar error 1060 — itu OK
        if ($e->getCode() === '42S21' || str_contains($e->getMessage(), 'Duplicate column')) {
            echo "– [{$name}] Kolom sudah ada, skip.\n";
        } else {
            echo "✗ [{$name}] GAGAL: " . $e->getMessage() . "\n";
            $allOk = false;
        }
    }
}

echo "\n";
if ($allOk) {
    echo "=== Semua migration selesai. Hapus file ini! ===\n";
} else {
    echo "=== Ada migration yang gagal. Periksa error di atas. ===\n";
}
echo "</pre>";
