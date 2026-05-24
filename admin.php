<?php
require_once __DIR__ . '/config/db.php';

// Базовая HTTP-авторизация для админки
if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== 'admin' || $_SERVER['PHP_AUTH_PW'] !== 'mega2026') {
    header('WWW-Authenticate: Basic realm="TaHunter Admin Area"');
    header('HTTP/1.0 401 Unauthorized');
    die('Доступ закрыт.');
}

$action = $_POST['action'] ?? '';

if ($action === 'toggle_game') {
    $status = (int)$_POST['status'];
    $pdo->prepare("UPDATE game_settings SET is_active = ?")->execute([$status]);
    
    // Если игру остановили, очищаем чат
    if ($status === 0) {
        $pdo->query("TRUNCATE TABLE chat_messages");
        $files = glob(__DIR__ . '/chat/*');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
    }
    
    header("Location: admin.php");
    exit;
}

if ($action === 'broadcast') {
    $msg = trim($_POST['message']);
    if ($msg) {
        $pdo->prepare("INSERT INTO broadcasts (message) VALUES (?)")->execute([$msg]);
    }
    header("Location: admin.php");
    exit;
}

if ($action === 'change_role') {
    $uid = (int)$_POST['user_id'];
    $new_role = $_POST['new_role'] === 'hunter' ? 'hunter' : 'hider';
    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $uid]);
    header("Location: admin.php");
    exit;
}

if ($action === 'give_coins') {
    $uid = (int)$_POST['user_id'];
    $amount = (int)$_POST['amount'];
    $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$amount, $uid]);
    header("Location: admin.php");
    exit;
}

if ($action === 'give_item') {
    $uid = (int)$_POST['user_id'];
    $item_code = $_POST['item_code'];
    
    $stmt = $pdo->prepare("SELECT duration_sec FROM items WHERE code = ?");
    $stmt->execute([$item_code]);
    $duration = $stmt->fetchColumn();
    
    if ($duration !== false) {
        $expires_at = $duration > 0 ? date('Y-m-d H:i:s', time() + $duration) : null;
        $pdo->prepare("INSERT INTO user_active_items (user_id, item_code, expires_at) VALUES (?, ?, ?)")
            ->execute([$uid, $item_code, $expires_at]);
    }
    header("Location: admin.php");
    exit;
}

if ($action === 'clear_chat') {
    $pdo->query("TRUNCATE TABLE chat_messages");
    $files = glob(__DIR__ . '/chat/*');
    foreach ($files as $file) {
        if (is_file($file)) unlink($file);
    }
    header("Location: admin.php");
    exit;
}

// Получение статистики
$settings = $pdo->query("SELECT * FROM game_settings LIMIT 1")->fetch();
$users = $pdo->query("SELECT id, nickname, role, status, coins, last_update FROM users ORDER BY role, id DESC")->fetchAll();
$items = $pdo->query("SELECT code, name, target_role FROM items ORDER BY target_role, name")->fetchAll();

// Получаем активные баффы всех
$active_buffs = [];
$buff_stmt = $pdo->query("SELECT u.user_id, i.name FROM user_active_items u JOIN items i ON u.item_code = i.code");
while ($row = $buff_stmt->fetch()) {
    $active_buffs[$row['user_id']][] = $row['name'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaHunter | COMMAND CENTER</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #0a0a0c; --card-bg: #16161a; --primary: #FFD700; --text: #e1e1e3; --danger: #ff4757; --success: #2ed573; --border: rgba(255, 215, 0, 0.15); }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn { background: var(--primary); color: #000; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-danger { background: var(--danger); color: #fff; }
        select, input { padding: 8px; border-radius: 6px; border: 1px solid #333; background: #222; color: #fff; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border-bottom: 1px solid #333; text-align: left; }
        .buff-tag { background: #333; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px; border: 1px solid var(--primary); margin: 2px; display: inline-block; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Command Center</h1>
        <h2>РАУНД: <?php echo $settings['is_active'] ? '<span style="color:var(--success)">ИДЕТ</span>' : '<span style="color:var(--danger)">ОСТАНОВЛЕН</span>'; ?></h2>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Управление</h3>
            <form method="POST" style="margin-bottom: 10px;">
                <input type="hidden" name="action" value="toggle_game">
                <input type="hidden" name="status" value="<?php echo $settings['is_active'] ? '0' : '1'; ?>">
                <button type="submit" class="btn <?php echo $settings['is_active'] ? 'btn-danger' : ''; ?>">
                    <?php echo $settings['is_active'] ? 'Остановить Игру (Удалит Чат)' : 'Запустить Игру'; ?>
                </button>
            </form>
            <form method="POST">
                <input type="hidden" name="action" value="clear_chat">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Точно удалить все медиа и сообщения?');">Очистить Чат Вручную</button>
            </form>
        </div>

        <div class="card">
            <h3>Push-Уведомление</h3>
            <form method="POST">
                <input type="hidden" name="action" value="broadcast">
                <input type="text" name="message" placeholder="Текст..." required style="width: 70%;">
                <button type="submit" class="btn">Отправить</button>
            </form>
        </div>
        
        <div class="card">
            <h3>Выдать Монеты</h3>
            <form method="POST">
                <input type="hidden" name="action" value="give_coins">
                <select name="user_id" required>
                    <option value="">-- Игрок --</option>
                    <?php foreach ($users as $u) echo "<option value='{$u['id']}'>{$u['nickname']} ({$u['role']})</option>"; ?>
                </select>
                <input type="number" name="amount" value="50" style="width: 80px;">
                <button type="submit" class="btn">Выдать</button>
            </form>
        </div>

        <div class="card">
            <h3>Выдать Предмет</h3>
            <form method="POST">
                <input type="hidden" name="action" value="give_item">
                <select name="user_id" required>
                    <option value="">-- Игрок --</option>
                    <?php foreach ($users as $u) echo "<option value='{$u['id']}'>{$u['nickname']} ({$u['role']})</option>"; ?>
                </select>
                <select name="item_code" required>
                    <option value="">-- Предмет --</option>
                    <?php foreach ($items as $i) echo "<option value='{$i['code']}'>{$i['name']} ({$i['target_role']})</option>"; ?>
                </select>
                <button type="submit" class="btn">Выдать</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h3>Игроки</h3>
        <table>
            <thead><tr><th>Игрок</th><th>Роль</th><th>Монеты</th><th>Активные Баффы</th><th>Сменить Роль</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo $u['nickname']; ?><br><small style="color:#888"><?php echo $u['last_update'] ?: 'Оффлайн'; ?></small></td>
                    <td><?php echo $u['role'] === 'hunter' ? '<span style="color:var(--danger)">Вода</span>' : '<span style="color:#70a1ff">Прятки</span>'; ?></td>
                    <td><?php echo $u['coins']; ?> 🪙</td>
                    <td>
                        <?php 
                        if (isset($active_buffs[$u['id']])) {
                            foreach ($active_buffs[$u['id']] as $b) {
                                echo "<span class='buff-tag'>$b</span>";
                            }
                        } else { echo "<small style='color:#666'>Нет</small>"; }
                        ?>
                    </td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <select name="new_role" onchange="this.form.submit()">
                                <option value="hider" <?php echo $u['role'] === 'hider' ? 'selected' : ''; ?>>Прятки</option>
                                <option value="hunter" <?php echo $u['role'] === 'hunter' ? 'selected' : ''; ?>>Вода</option>
                            </select>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>