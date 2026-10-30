<?php
session_start();
session_unset();
session_destroy();
// Hapus semua cookie client-side
setcookie('tk_logged',  '', time() - 3600, '/');
setcookie('tk_userid',  '', time() - 3600, '/');
setcookie('tk_nama',    '', time() - 3600, '/');
header('Location: ../routes/login.html');
exit;
