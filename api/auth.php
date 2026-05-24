<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'register') {
    $nickname = trim($_POST['nickname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($nickname) || empty($phone) || empty($password)) {
        die("Заполните все поля. <a href='/'>Назад</a>");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (phone, nickname, password) VALUES (?, ?, ?)");
        $stmt->execute([$phone, $nickname, $hash]);
        
        // Автологин после регистрации
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['nickname'] = $nickname;
        $_SESSION['role'] = 'hider';
        $_SESSION['coins'] = 0;
        
        header("Location: /index.php");
        exit;
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
            die("Пользователь с таким телефоном или никнеймом уже существует. <a href='/'>Назад</a>");
        }
        die("Ошибка регистрации: " . $e->getMessage());
    }
} elseif ($action === 'login') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nickname'] = $user['nickname'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['coins'] = $user['coins'];
        
        header("Location: /index.php");
        exit;
    } else {
        die("Неверный телефон или пароль. <a href='/'>Назад</a>");
    }
} elseif ($action === 'logout') {
    session_destroy();
    header("Location: /index.php");
    exit;
}

die("Неверное действие.");