<?php

session_start();

// Belum login
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../routes/login.html?error=auth');
    exit;
}

// Login tapi bukan admin
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../routes/homePageUser.html');
    exit;
}

// Admin terverifikasi → arahkan ke dashboard
header('Location: ../routes/dashboard.html');
exit;
