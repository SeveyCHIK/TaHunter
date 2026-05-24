<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Auth required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$action = $_POST['action'] ?? '';

if ($action === 'get_items') {
    // Получаем ВСЕ предметы
    $stmt = $pdo->query("SELECT * FROM items ORDER BY target_role DESC, cost ASC");
    $items = $stmt->fetchAll();
    echo json_encode(['items' => $items, 'my_role' => $role]);
} elseif ($action === 'buy') {
    $item_code = $_POST['item_code'];
    
    // Получаем предмет
    $stmt = $pdo->prepare("SELECT * FROM items WHERE code = ? AND (target_role = ? OR target_role = 'all')");
    $stmt->execute([$item_code, $role]);
    $item = $stmt->fetch();

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Предмет недоступен для вашей роли']);
        exit;
    }

    // Проверка баланса
    $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $coins = (int)$stmt->fetchColumn();

    if ($coins < $item['cost']) {
        echo json_encode(['success' => false, 'error' => 'Недостаточно монет']);
        exit;
    }

    // Логика блокировки покупки Завесы, если Вода включила Генератор помех
    if ($item_code === 'smoke') {
        $stmt = $pdo->query("SELECT id FROM user_active_items WHERE item_code = 'jammer'");
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Генератор помех Воды блокирует покупку Завесы!']);
            exit;
        }
    }

    // Транзакция покупки
    try {
        $pdo->beginTransaction();
        
        // Списание монет
        $stmt = $pdo->prepare("UPDATE users SET coins = coins - ? WHERE id = ?");
        $stmt->execute([$item['cost'], $user_id]);
        
        // Активация предмета
        $expires_at = $item['duration_sec'] > 0 ? date('Y-m-d H:i:s', time() + $item['duration_sec']) : null;
        $stmt = $pdo->prepare("INSERT INTO user_active_items (user_id, item_code, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $item_code, $expires_at]);
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Ошибка базы данных']);
    }
}
?>