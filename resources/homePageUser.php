<?php

session_start();

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    setcookie('tk_logged',  '', time() - 3600, '/');
    setcookie('tk_userid',  '', time() - 3600, '/');
    setcookie('tk_nama',    '', time() - 3600, '/');
    header('Location: ../routes/login.html?error=auth');
    exit;
}

// Cookie sinyal (non-HttpOnly agar JS bisa baca)
setcookie('tk_logged', '1', 0, '/', '', false, false);
setcookie('tk_userid', (string)($_SESSION['user_id']   ?? '0'), 0, '/', '', false, false);
setcookie('tk_nama',   rawurlencode($_SESSION['user_nama'] ?? 'Pelanggan'), 0, '/', '', false, false);

header('Location: ../routes/homePageUser.html');
exit;
