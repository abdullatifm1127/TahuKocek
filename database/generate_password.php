<?php
/**
 * Jalankan SEKALI untuk mendapatkan hash password yang benar,
 * lalu UPDATE tabel users secara manual atau via phpmyadmin.
 *
 * Akses: http://localhost/TahuKocek/database/generate_password.php
 * Hapus file ini setelah digunakan!
 */

$passwords = [
    'admin@tahukocek.com' => 'tahu123',
    'user@tahukocek.com'  => 'user123',
];

echo "<pre>\n";
echo "-- Jalankan SQL ini di phpMyAdmin atau MySQL client:\n\n";

foreach ($passwords as $email => $plain) {
    $hash = password_hash($plain, PASSWORD_DEFAULT);
    echo "UPDATE users SET password = '$hash' WHERE email = '$email';\n";
}

echo "\n-- Setelah dijalankan, hapus file ini!\n";
echo "</pre>";
