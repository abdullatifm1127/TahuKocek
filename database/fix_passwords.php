<?php
require_once __DIR__ . '/../resources/db.php';

$users = [
    'admin@tahukocek.com' => 'tahu123',
    'user@tahukocek.com'  => 'user123',
];

echo '<pre style="font-family:monospace;padding:24px;font-size:14px">';
echo "=== Fix Password TahuKocek ===\n\n";

try {
    $pdo = db();

    foreach ($users as $email => $plain) {
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
        $affected = $stmt->execute([$hash, $email]);
        $rows = $stmt->rowCount();

        if ($rows > 0) {
            echo "✓ {$email} → password '{$plain}' berhasil diupdate\n";
        } else {
            // User belum ada, INSERT
            $ins = $pdo->prepare(
                "INSERT INTO users (nama, email, phone, password, role) VALUES (?, ?, '', ?, ?)"
            );
            $nama = str_contains($email, 'admin') ? 'Dina Putri' : 'Pelanggan';
            $role = str_contains($email, 'admin') ? 'admin' : 'user';
            $ins->execute([$nama, $email, $hash, $role]);
            echo "+ {$email} → user baru dibuat dengan password '{$plain}'\n";
        }
    }

    echo "\n=== Selesai! Coba login sekarang. ===\n";
    echo "\nAkun yang tersedia:\n";
    echo "  Admin : admin\@tahukocek.com / tahu123\n";
    echo "  User  : user\@tahukocek.com  / user123\n";
    echo "\n⚠ HAPUS FILE INI SETELAH SELESAI!\n";

} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "\nPastikan:\n";
    echo "  1. Database 'tahukocek' sudah dibuat\n";
    echo "  2. Tabel 'users' sudah ada (jalankan database/tahukocek.sql dulu)\n";
    echo "  3. Konfigurasi DB_HOST, DB_USER, DB_PASS di resources/db.php sudah benar\n";
}

echo '</pre>';
