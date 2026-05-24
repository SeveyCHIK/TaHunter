<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$action = $_POST['action'] ?? '';

// Очистка просроченных предметов
$pdo->query("DELETE FROM user_active_items WHERE expires_at IS NOT NULL AND expires_at < NOW()");

if ($action === 'activate_invis') {
    if ($role !== 'hunter') {
        echo json_encode(['success' => false, 'error' => 'Только для Воды']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT in_invis_until, invis_cooldown_until FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();

    if ($u['invis_cooldown_until'] && strtotime($u['invis_cooldown_until']) > time()) {
        echo json_encode(['success' => false, 'error' => 'Навык в откате']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT item_code FROM user_active_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $my_items = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $duration = 60; // 1 минута базово
    $cooldown = 600; // 10 минут базово

    if (in_array('boost', $my_items)) {
        $duration = 120; // 2 минуты
    }
    if (in_array('sprinter', $my_items)) {
        $cooldown = 300; // 5 минут
    }

    $in_invis_until = date('Y-m-d H:i:s', time() + $duration);
    $cooldown_until = date('Y-m-d H:i:s', time() + $cooldown);

    $stmt = $pdo->prepare("UPDATE users SET in_invis_until = ?, invis_cooldown_until = ? WHERE id = ?");
    $stmt->execute([$in_invis_until, $cooldown_until, $user_id]);

    echo json_encode(['success' => true]);
    exit;
}


// Получение настроек
$stmt = $pdo->query("SELECT * FROM game_settings ORDER BY id DESC LIMIT 1");
$settings = $stmt->fetch();
$base_delay = (int)($settings['hider_delay_sec'] ?? 10);
$is_active = (bool)$settings['is_active'];

if ($action === 'game_tick') {
    $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
    $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

    // Обновляем позицию, если игра активна и пришли координаты
    if ($is_active && $lat && $lng) {
        $stmt = $pdo->prepare("SELECT id FROM user_active_items WHERE user_id = ? AND item_code = 'phantom'");
        $stmt->execute([$user_id]);
        $has_phantom = $stmt->fetch();

        // Пишем в историю ВСЕГДА (даже если фантом, история содержит реальный путь для дешифратора)
        $stmt = $pdo->prepare("INSERT INTO location_history (user_id, lat, lng) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $lat, $lng]);

        // Обновляем текущие координаты (только если нет фантома)
        if (!$has_phantom) {
            $stmt = $pdo->prepare("UPDATE users SET lat = ?, lng = ?, last_update = NOW() WHERE id = ?");
            $stmt->execute([$lat, $lng, $user_id]);
        }
    }

    // Читаем активные баффы текущего игрока
    $stmt = $pdo->prepare("SELECT item_code FROM user_active_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $my_items = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $response = ['players' => [], 'is_active' => $is_active, 'my_buffs' => []];

    // Загрузка деталей баффов для инвентаря
    if (!empty($my_items)) {
        $in = str_repeat('?,', count($my_items) - 1) . '?';
        $stmt = $pdo->prepare("SELECT name, code FROM items WHERE code IN ($in)");
        $stmt->execute($my_items);
        $response['my_buffs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Для интерфейса Воды
    if ($role === 'hunter') {
        $stmt = $pdo->prepare("SELECT in_invis_until, invis_cooldown_until FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $u = $stmt->fetch();
        $response['in_invis_until'] = $u['in_invis_until'];
        $response['invis_cooldown_until'] = $u['invis_cooldown_until'];
    }

    if ($is_active) {
        if ($role === 'hunter') {
            $has_decoder = in_array('decoder', $my_items);

            $stmt = $pdo->query("SELECT id, nickname, lat, lng FROM users WHERE role = 'hider' AND status = 'active'");
            $hiders = $stmt->fetchAll();

            foreach ($hiders as $h) {
                // Получаем баффы конкретного прячущегося
                $chk = $pdo->prepare("SELECT item_code FROM user_active_items WHERE user_id = ?");
                $chk->execute([$h['id']]);
                $h_buffs = $chk->fetchAll(PDO::FETCH_COLUMN);

                if (!$has_decoder && in_array('smoke', $h_buffs)) {
                    continue; // Скрыт дымовой завесой
                }

                $h_lat = $h['lat'];
                $h_lng = $h['lng'];

                // Если Дешифратор и есть Фантом - берем реальные последние
                if ($has_decoder && in_array('phantom', $h_buffs)) {
                    $real = $pdo->prepare("SELECT lat, lng FROM location_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
                    $real->execute([$h['id']]);
                    if ($r = $real->fetch()) {
                        $h_lat = $r['lat'];
                        $h_lng = $r['lng'];
                    }
                }

                // Если Адреналин - отдаем координаты с задержкой 30с
                if (in_array('adrenaline', $h_buffs)) {
                    $delay_q = $pdo->prepare("SELECT lat, lng FROM location_history WHERE user_id = ? AND created_at <= (NOW() - INTERVAL 30 SECOND) ORDER BY created_at DESC LIMIT 1");
                    $delay_q->execute([$h['id']]);
                    if ($d = $delay_q->fetch()) {
                        $h_lat = $d['lat'];
                        $h_lng = $d['lng'];
                    } else {
                        continue; // Еще не прошло 30 сек
                    }
                }

                if ($h_lat && $h_lng) {
                    $response['players'][] = [
                        'id' => $h['id'],
                        'nickname' => $h['nickname'],
                        'role' => 'hider',
                        'lat' => $h_lat,
                        'lng' => $h_lng
                    ];
                }
            }
        } else {
            // Логика Hider (Прячущегося)
            $delay = $base_delay;

            $stmt = $pdo->query("SELECT user_id FROM user_active_items WHERE item_code = 'predator' LIMIT 1");
            $predator_active = $stmt->fetch();

            if (!$predator_active && in_array('radar', $my_items)) {
                $delay = 5;
            }

            $stmt = $pdo->query("SELECT id, nickname, lat, lng, in_invis_until FROM users WHERE role = 'hunter' LIMIT 1");
            $hunter = $stmt->fetch();

            if ($hunter) {
                $is_invis = ($hunter['in_invis_until'] && strtotime($hunter['in_invis_until']) > time());
                $can_see = true;

                if ($is_invis) {
                    if (!$predator_active && in_array('sixth_sense', $my_items)) {
                        $delay = 20; // Видит в инвизе, но большая задержка
                    } else {
                        $can_see = false;
                    }
                }

                if ($can_see) {
                    $hist_stmt = $pdo->prepare("SELECT lat, lng FROM location_history WHERE user_id = ? AND created_at <= (NOW() - INTERVAL ? SECOND) ORDER BY created_at DESC LIMIT 1");
                    $hist_stmt->execute([$hunter['id'], $delay]);
                    $hist = $hist_stmt->fetch();

                    if ($hist) {
                        $response['players'][] = [
                            'id' => $hunter['id'],
                            'nickname' => 'Вода',
                            'role' => 'hunter',
                            'lat' => $hist['lat'],
                            'lng' => $hist['lng']
                        ];
                    }
                }
            }
        }
    }

    // Проверка броадкастов (последний за 1 минуту)
    $stmt = $pdo->query("SELECT message FROM broadcasts WHERE created_at >= (NOW() - INTERVAL 1 MINUTE) ORDER BY id DESC LIMIT 1");
    $broadcast = $stmt->fetch();
    $response['broadcast'] = $broadcast ? $broadcast['message'] : null;

    // Баланс
    $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $response['coins'] = $stmt->fetchColumn();
    $_SESSION['coins'] = $response['coins'];

    echo json_encode($response);
}
?>