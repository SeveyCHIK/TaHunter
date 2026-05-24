CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `nickname` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('hider','hunter') NOT NULL DEFAULT 'hider',
  `status` enum('active','caught') NOT NULL DEFAULT 'active',
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `last_update` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `coins` int(11) NOT NULL DEFAULT 0,
  `in_invis_until` timestamp NULL DEFAULT NULL,
  `invis_cooldown_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `nickname` (`nickname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('text','image','voice') NOT NULL DEFAULT 'text',
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `game_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `hider_delay_sec` int(11) NOT NULL DEFAULT 10,
  `start_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `target_role` enum('hider','hunter','all') NOT NULL,
  `cost` int(11) NOT NULL,
  `description` text NOT NULL,
  `duration_sec` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_active_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `activated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `broadcasts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Базовый инсерт настроек
INSERT INTO `game_settings` (`is_active`, `hider_delay_sec`) VALUES (0, 10);

-- Базовый инсерт предметов (Арсенал)
INSERT INTO `items` (`code`, `name`, `target_role`, `cost`, `description`, `duration_sec`) VALUES
('radar', 'Радар', 'hider', 30, 'Задержка Воды 5 сек вместо 10.', 120),
('sixth_sense', 'Шестое чувство', 'hider', 80, 'Видит Воду в «Инвизе» (задержка 20 сек).', 120),
('smoke', 'Дымовая завеса', 'hider', 50, 'Метка исчезает у Воды на 60 сек.', 60),
('phantom', 'Фантом', 'hider', 100, 'Метка замораживается на 2 мин в текущей точке.', 120),
('adrenaline', 'Адреналин', 'hider', 60, 'Координаты у Воды обновляются раз в 30 сек.', 120),
('sprinter', 'Спринтер', 'hunter', 40, 'Кулдаун «Инвиза» снижен на 5 мин.', 0),
('predator', 'Хищник', 'hunter', 80, 'Отключает Радар и Шестое чувство у всех на 2 мин.', 120),
('decoder', 'Дешифратор', 'hunter', 80, 'Игнорирует Фантом и Завесу.', 120),
('jammer', 'Генератор помех', 'hunter', 70, 'Блокирует покупку Завесы.', 120),
('boost', 'Форсаж', 'hunter', 90, 'Инвиз длится 2 мин.', 120);
