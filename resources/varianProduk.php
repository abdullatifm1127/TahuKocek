<?php

session_start();

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../routes/login.html?error=auth');
    exit;
}

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../routes/homePageUser.html');
    exit;
}

header('Location: ../routes/varianProduk.html');
exit;
