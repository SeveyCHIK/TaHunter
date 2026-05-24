<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Чат работает только во время раунда
$stmt = $pdo->query("SELECT is_active FROM game_settings ORDER BY id DESC LIMIT 1");
$settings = $stmt->fetch();
if (empty($settings['is_active'])) {
    echo json_encode(['error' => 'Чат доступен только во время активного раунда.']);
    exit;
}

if ($action === 'get') {
    $stmt = $pdo->query("
        SELECT c.*, u.nickname 
        FROM chat_messages c
        JOIN users u ON c.user_id = u.id
        ORDER BY c.created_at ASC
    ");
    $messages = [];
    while ($row = $stmt->fetch()) {
        $messages[] = [
            'id' => $row['id'],
            'nickname' => $row['nickname'],
            'is_me' => ($row['user_id'] == $user_id),
            'type' => $row['type'],
            'content' => $row['content'],
            'time' => date('H:i', strtotime($row['created_at']))
        ];
    }
    echo json_encode(['messages' => $messages]);
    exit;
}

if ($action === 'send') {
    $type = 'text';
    $content = '';

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $mime = mime_content_type($fileTmp);
        
        if (strpos($mime, 'image/') === 0) {
            $type = 'image';
        } elseif (strpos($mime, 'audio/') === 0 || strpos($mime, 'video/') === 0) {
            // Кружочки/Голосовухи часто распознаются как audio или video
            $type = 'voice';
        } else {
            echo json_encode(['success' => false, 'error' => 'Неподдерживаемый формат файла.']);
            exit;
        }

        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $newName = uniqid('chat_', true) . '.' . $ext;
        $dest = __DIR__ . '/../chat/' . $newName;

        if (move_uploaded_file($fileTmp, $dest)) {
            $content = '/chat/' . $newName;
        } else {
            echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла.']);
            exit;
        }
    } else {
        $content = trim($_POST['message'] ?? '');
        if ($content === '') {
            echo json_encode(['success' => false, 'error' => 'Пустое сообщение.']);
            exit;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, type, content) VALUES (?, ?, ?)");
    if ($stmt->execute([$user_id, $type, $content])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка БД.']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>