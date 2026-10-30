<?php

session_start();
require_once __DIR__ . '/db.php';

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../routes/login.html');
    exit;
}

$email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL));
$password = trim(filter_input(INPUT_POST, 'password', FILTER_DEFAULT));

// Validasi dasar
if (empty($email) || empty($password) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../routes/login.html?error=1');
    exit;
}

try {
    $pdo  = db();
    $stmt = $pdo->prepare('SELECT id, nama, email, password, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower($email)]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID untuk mencegah session fixation
        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_nama']  = $user['nama'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['logged_in']  = true;

        if ($user['role'] === 'admin') {
            header('Location: ../resources/dashboard.php');
        } else {
            header('Location: ../resources/homePageUser.php');
        }
        exit;
    }

    header('Location: ../routes/login.html?error=1');
    exit;

} catch (PDOException $e) {
    header('Location: ../routes/login.html?error=server');
    exit;
}
