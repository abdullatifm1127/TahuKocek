<?php
/**
 * debug_login.php – Diagnosa lengkap masalah login
 * Akses: http://localhost/TahuKocek/database/debug_login.php
 * HAPUS setelah selesai!
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<style>body{font-family:monospace;padding:20px;} .ok{color:green} .err{color:red} .warn{color:orange} pre{background:#f5f5f5;padding:12px;border-radius:6px}</style>';
echo '<h2>🔍 Debug Login TahuKocek</h2>';

// ── 1. Cek koneksi DB ──
echo '<h3>1. Koneksi Database</h3>';
$host = 'localhost'; $db = 'tahukocek'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo '<p class="ok">✓ Koneksi ke database "tahukocek" berhasil</p>';
} catch (PDOException $e) {
    echo '<p class="err">✗ Koneksi gagal: ' . $e->getMessage() . '</p>';
    echo '<p class="warn">→ Pastikan Laragon berjalan dan database "tahukocek" sudah dibuat</p>';
    exit;
}

// ── 2. Cek tabel users ──
echo '<h3>2. Tabel Users</h3>';
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    if (empty($tables)) {
        echo '<p class="err">✗ Tabel "users" TIDAK ADA</p>';
        echo '<p class="warn">→ Import database/tahukocek.sql terlebih dahulu</p>';

        // Buat tabel otomatis
        echo '<p class="warn">→ Mencoba membuat tabel users otomatis...</p>';
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(150) NOT NULL,
            email VARCHAR(191) NOT NULL UNIQUE,
            phone VARCHAR(20) NOT NULL DEFAULT '',
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','user') NOT NULL DEFAULT 'user',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        echo '<p class="ok">✓ Tabel users berhasil dibuat</p>';
    } else {
        echo '<p class="ok">✓ Tabel "users" ada</p>';
    }
} catch (PDOException $e) {
    echo '<p class="err">✗ ' . $e->getMessage() . '</p>';
}

// ── 3. Cek & buat/update user ──
echo '<h3>3. Data User</h3>';
$accounts = [
    ['nama'=>'Dina Putri',  'email'=>'admin@tahukocek.com', 'password'=>'tahu123', 'role'=>'admin'],
    ['nama'=>'Pelanggan',   'email'=>'user@tahukocek.com',  'password'=>'user123', 'role'=>'user'],
];

foreach ($accounts as $acc) {
    $stmt = $pdo->prepare('SELECT id, email, password, role FROM users WHERE email = ?');
    $stmt->execute([$acc['email']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $verify = password_verify($acc['password'], $row['password']);
        if ($verify) {
            echo '<p class="ok">✓ ' . $acc['email'] . ' → password_verify OK (role: ' . $row['role'] . ')</p>';
        } else {
            echo '<p class="err">✗ ' . $acc['email'] . ' → password_verify GAGAL — memperbaiki hash...</p>';
            $hash = password_hash($acc['password'], PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = ?, role = ? WHERE email = ?')
                ->execute([$hash, $acc['role'], $acc['email']]);
            echo '<p class="ok">  ✓ Hash diperbarui. Coba password: ' . $acc['password'] . '</p>';
        }
    } else {
        echo '<p class="warn">⚠ ' . $acc['email'] . ' belum ada — membuat user baru...</p>';
        $hash = password_hash($acc['password'], PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (nama,email,phone,password,role) VALUES (?,?,?,?,?)')
            ->execute([$acc['nama'], $acc['email'], '', $hash, $acc['role']]);
        echo '<p class="ok">  ✓ User dibuat. Password: ' . $acc['password'] . '</p>';
    }
}

// ── 4. Tampilkan semua user ──
echo '<h3>4. Semua User di Database</h3>';
$rows = $pdo->query('SELECT id, nama, email, role, LEFT(password,30) as hash_preview FROM users')->fetchAll(PDO::FETCH_ASSOC);
echo '<pre>';
foreach ($rows as $r) {
    echo "ID:{$r['id']} | {$r['email']} | {$r['role']} | hash: {$r['hash_preview']}...\n";
}
echo '</pre>';

// ── 5. Cek db.php path ──
echo '<h3>5. Cek resources/db.php</h3>';
$dbPath = __DIR__ . '/../resources/db.php';
if (file_exists($dbPath)) {
    echo '<p class="ok">✓ db.php ditemukan di: ' . realpath($dbPath) . '</p>';
    require_once $dbPath;
    try {
        $pdo2 = db();
        echo '<p class="ok">✓ Fungsi db() berfungsi normal</p>';
    } catch (Exception $e) {
        echo '<p class="err">✗ db() error: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p class="err">✗ db.php TIDAK DITEMUKAN di: ' . $dbPath . '</p>';
}

// ── 6. Tes simulasi login ──
echo '<h3>6. Simulasi Login</h3>';
foreach ($accounts as $acc) {
    $stmt = $pdo->prepare('SELECT id, nama, password, role FROM users WHERE email = ?');
    $stmt->execute([strtolower($acc['email'])]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u && password_verify($acc['password'], $u['password'])) {
        echo '<p class="ok">✓ Login "' . $acc['email'] . '" dengan password "' . $acc['password'] . '" → BERHASIL (role: ' . $u['role'] . ')</p>';
    } else {
        echo '<p class="err">✗ Login "' . $acc['email'] . '" → GAGAL</p>';
    }
}

echo '<hr><h3 class="ok">✅ Selesai! Coba login sekarang.</h3>';
echo '<p><a href="../routes/login.html">→ Ke halaman login</a></p>';
echo '<p class="err">⚠ Hapus file ini setelah selesai: database/debug_login.php</p>';
