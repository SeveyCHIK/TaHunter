<?php
// config/db.php
$host = 'localhost'; // SpaceWeb DB host (usually localhost)
$db   = 'dagazautoy'; // Change to actual SpaceWeb DB name
$user = 'dagazautoy'; // Change to SpaceWeb DB user
$pass = 'Tamir657'; // Change to SpaceWeb DB pass
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // В продакшене лучше логировать ошибку, не выводите её прямо на экран
    error_log($e->getMessage());
    die("Ошибка подключения к базе данных. Попробуйте позже.");
}
?>