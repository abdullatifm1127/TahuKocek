<?php

session_start();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../routes/register.html');
    exit;
}

$nama     = trim(filter_input(INPUT_POST, 'nama',             FILTER_DEFAULT));
$email    = trim(filter_input(INPUT_POST, 'email',            FILTER_SANITIZE_EMAIL));
$phone    = trim(filter_input(INPUT_POST, 'phone',            FILTER_DEFAULT));
$password = trim(filter_input(INPUT_POST, 'password',         FILTER_DEFAULT));
$confirm  = trim(filter_input(INPUT_POST, 'confirm_password', FILTER_DEFAULT));
$agree    = isset($_POST['agree']);

// Validasi server-side
if (empty($nama))                                         { header('Location: ../routes/register.html?error=server'); exit; }
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { header('Location: ../routes/register.html?error=server'); exit; }
if (empty($phone) || !preg_match('/^08\d{7,13}$/', $phone))      { header('Location: ../routes/register.html?error=server'); exit; }
if (strlen($password) < 8)                               { header('Location: ../routes/register.html?error=server'); exit; }
if ($password !== $confirm)                              { header('Location: ../routes/register.html?error=server'); exit; }
if (!$agree)                                             { header('Location: ../routes/register.html?error=server'); exit; }

try {
    $pdo = db();

    // Cek email duplikat
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $chk->execute([strtolower($email)]);
    if ($chk->fetch()) {
        header('Location: ../routes/register.html?error=exists');
        exit;
    }

    // INSERT user baru
    $ins = $pdo->prepare(
        'INSERT INTO users (nama, email, phone, password, role) VALUES (?, ?, ?, ?, ?)'
    );
    $ins->execute([
        htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'),
        strtolower($email),
        $phone,
        password_hash($password, PASSWORD_DEFAULT),
        'user',
    ]);

    $newId = (int) $pdo->lastInsertId();

    session_regenerate_id(true);
    $_SESSION['user_id']    = $newId;
    $_SESSION['user_email'] = strtolower($email);
    $_SESSION['user_nama']  = htmlspecialchars($nama, ENT_QUOTES, 'UTF-8');
    $_SESSION['user_role']  = 'user';
    $_SESSION['logged_in']  = true;

    header('Location: ../resources/homePageUser.php');
    exit;

} catch (PDOException $e) {
    header('Location: ../routes/register.html?error=server');
    exit;
}
