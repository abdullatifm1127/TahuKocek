<?php

session_start();

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../routes/login.html');
    exit;
}

if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: ../routes/dashboard.html');
    exit;
}

header('Location: ../routes/homePageUser.html');
exit;
