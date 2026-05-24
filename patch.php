<?php
// patch.php - Скрипт для настройки базы данных и прав на папки
require_once __DIR__ . '/config/db.php';

echo "<h2>Установка TaHunter...</h2>";

// 1. Создаем папку chat и выдаем права
$chatDir = __DIR__ . '/chat';
if (!is_dir($chatDir)) {
    mkdir($chatDir, 0777, true);
    echo "Папка chat/ создана.<br>";
} else {
    chmod($chatDir, 0777);
    echo "Права на папку chat/ обновлены.<br>";
}

// 2. Обновляем базу данных
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `chat_messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `type` enum('text','image','voice') NOT NULL DEFAULT 'text',
        `content` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Таблица chat_messages готова.<br>";

    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `invis_cooldown_until` timestamp NULL DEFAULT NULL AFTER `in_invis_until`");
        echo "Колонка invis_cooldown_until добавлена.<br>";
    } catch(PDOException $e) {
        // Игнорируем ошибку, если колонка уже есть
    }

    $pdo->exec("TRUNCATE TABLE `items`");
    $pdo->exec("INSERT INTO `items` (`code`, `name`, `target_role`, `cost`, `description`, `duration_sec`) VALUES
        ('radar', 'Радар', 'hider', 30, 'Задержка Воды 5 сек вместо 10.', 120),
        ('sixth_sense', 'Шестое чувство', 'hider', 80, 'Видит Воду в «Инвизе» (задержка 20 сек).', 120),
        ('smoke', 'Дымовая завеса', 'hider', 50, 'Метка исчезает у Воды на 60 сек.', 60),
        ('phantom', 'Фантом', 'hider', 100, 'Метка замораживается на 2 мин в текущей точке.', 120),
        ('adrenaline', 'Адреналин', 'hider', 60, 'Координаты у Воды обновляются раз в 30 сек.', 120),
        ('sprinter', 'Спринтер', 'hunter', 40, 'Кулдаун «Инвиза» -5 мин.', 0),
        ('predator', 'Хищник', 'hunter', 80, 'Отключает Радар и Шестое чувство у всех на 2 мин.', 120),
        ('decoder', 'Дешифратор', 'hunter', 80, 'Игнорирует Фантом и Завесу.', 120),
        ('jammer', 'Генератор помех', 'hunter', 70, 'Блокирует покупку Завесы.', 120),
        ('boost', 'Форсаж', 'hunter', 90, 'Инвиз длится 2 мин.', 120)");
    echo "Предметы успешно добавлены в базу магазина.<br>";

    echo "<h3 style='color:green'>УСПЕХ! Всё настроено. Удалите этот файл patch.php из соображений безопасности.</h3>";
} catch (Exception $e) {
    echo "<h3 style='color:red'>Ошибка БД: " . $e->getMessage() . "</h3>";
}
?>