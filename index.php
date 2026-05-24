<?php
session_start();
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TaHunter</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/assets/css/chat_style.css?v=<?php echo time(); ?>">
    <?php if ($is_logged_in): ?>
    <!-- Yandex Maps API v2.1 (Бесплатный тариф) -->
    <script src="https://api-maps.yandex.ru/2.1/?apikey=21d01150-f1ff-4841-b0db-6e69123847cd&lang=ru_RU" type="text/javascript"></script>
    <?php endif; ?>
</head>
<body>

<div id="toastNotification" class="toast"></div>

<!-- Карманный режим (Чёрный экран) -->
<div id="pocketModeOverlay">
    <p>РАДАР АКТИВЕН<br><br>ФОНОВЫЙ РЕЖИМ</p>
    <button id="btnUnlock" class="unlock-btn">РАЗБЛОКИРОВАТЬ</button>
</div>

<?php if (!$is_logged_in): ?>
    <!-- ЭКРАН АВТОРИЗАЦИИ -->
    <div class="auth-container">
        <h1 class="logo">TaHunter</h1>
        
        <div class="auth-box" id="loginFormBox">
            <form id="loginForm" method="POST" action="/api/auth.php">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>ТЕЛЕФОН</label>
                    <input type="tel" name="phone" required placeholder="79991234567">
                </div>
                <div class="form-group">
                    <label>ПАРОЛЬ</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn">ВОЙТИ</button>
            </form>
            <div class="auth-switch">
                <a href="#" id="linkToRegister">НОВЫЙ АГЕНТ? РЕГИСТРАЦИЯ</a>
            </div>
        </div>

        <div class="auth-box" id="registerFormBox" style="display: none;">
            <form id="registerForm" method="POST" action="/api/auth.php">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label>ПОЗЫВНОЙ</label>
                    <input type="text" name="nickname" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>ТЕЛЕФОН</label>
                    <input type="tel" name="phone" required placeholder="79991234567">
                </div>
                <div class="form-group">
                    <label>ПАРОЛЬ</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn">СОЗДАТЬ ПРОФИЛЬ</button>
            </form>
            <div class="auth-switch">
                <a href="#" id="linkToLogin">УЖЕ АГЕНТ? ВОЙТИ</a>
            </div>
        </div>
    </div>
    
<?php else: ?>
    <!-- ГЛАВНЫЙ ЭКРАН ИГРЫ (APP) -->
    <div class="app-container">
        
        <!-- Полноэкранная карта -->
        <div id="mapContainer"></div>

        <!-- Верхний плавающий HUD -->
        <div class="hud-top">
            <div class="hud-role" id="hudRoleText">
                <?php if($_SESSION['role'] === 'hunter'): ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#ff3333"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"/></svg> ВОДА
                <?php else: ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#70a1ff"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> ПРЯТКИ
                <?php endif; ?>
            </div>
            <div class="hud-coins">
                <span id="coinBalance"><?php echo (int)$_SESSION['coins']; ?></span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#FFD700"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8zm1-13h-2v2H9v2h2v2h-2v2h2v2h2v-2h2v-2h-2v-2h2V9h-2z"/></svg>
            </div>
        </div>

        <!-- Статус ожидания игры -->
        <div id="gameStatusIndicator" class="game-status active">ОЖИДАНИЕ АДМИНА...</div>

        <!-- Кнопка Инвиза для Воды -->
        <?php if($_SESSION['role'] === 'hunter'): ?>
        <div id="invisBtnContainer" class="hud-invis">
            <button id="btnInvis" class="btn btn-danger pulse-anim" style="border-radius: 50%; width: 60px; height: 60px; padding: 0; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 15px rgba(255,51,51,0.5);">
                <!-- Ninja/Ghost SVG instead of Eye -->
                <svg width="30" height="30" viewBox="0 0 24 24" fill="#fff"><path d="M12 2C8.13 2 5 5.13 5 9v4H4v6h16v-6h-1V9c0-3.87-3.13-7-7-7zm0 2c2.76 0 5 2.24 5 5v4H7V9c0-2.76 2.24-5 5-5zm-2 5.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm4 0a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/></svg>
            </button>
            <div id="invisCooldownText" style="text-align: center; font-size: 0.7rem; font-weight: bold; margin-top: 5px; text-shadow: 1px 1px 2px #000;">ИНВИЗ</div>
        </div>
        <?php endif; ?>

        <!-- Нижняя навигация -->
        <div class="bottom-nav">
            <button class="nav-item" id="btnPocketMode">
                <svg class="nav-icon" viewBox="0 0 24 24"><path d="M15.67 4H14V2h-4v2H8.33C7.6 4 7 4.6 7 5.33v15.33C7 21.4 7.6 22 8.33 22h7.33c.74 0 1.34-.6 1.34-1.33V5.33C17 4.6 16.4 4 15.67 4zM11 20v-5.5H9L13 7v5.5h2L11 20z"/></svg>
                <span class="nav-text">КАРМАН</span>
            </button>
            <button class="nav-item" id="btnChat">
                <svg class="nav-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
                <span class="nav-text">ЧАТ</span>
            </button>
            <button class="nav-item highlight" id="btnStore">
                <svg class="nav-icon" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                <span class="nav-text">АРСЕНАЛ</span>
            </button>
            <button class="nav-item" id="btnSettings">
                <svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <span class="nav-text">ПРОФИЛЬ</span>
            </button>
        </div>
    </div>

    <!-- Модалка: Чат -->
    <div id="chatModal" class="modal anim-fade">
        <div class="modal-content chat-content slide-up">
            <div class="modal-header">
                <h2>РАЦИЯ</h2>
                <span class="close-modal" id="closeChat">X</span>
            </div>
            <div id="chatMessages" class="chat-messages">
                <!-- Сообщения -->
            </div>
            <div class="chat-input-area">
                <form id="chatForm" style="display: flex; width: 100%; gap: 5px; align-items: center;">
                    <label for="chatFile" class="chat-file-btn" style="flex-shrink:0;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="#fff"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                    </label>
                    <input type="file" id="chatFile" accept="image/*" style="display: none;">
                    
                    <button type="button" id="btnRecordCircle" class="chat-file-btn" style="flex-shrink:0; background:var(--primary-dark); border:none;" title="Зажать для кружка">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="#000"><circle cx="12" cy="12" r="8"/></svg>
                    </button>
                    
                    <input type="text" id="chatInput" placeholder="Текст..." autocomplete="off" style="flex-grow:1; min-width:0; padding:10px;">
                    <button type="submit" class="btn btn-sm" style="flex-shrink:0; width:45px; height:45px; margin:0; padding:0; display:flex; justify-content:center; align-items:center;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="#000"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </form>
            </div>
            <div id="recordingIndicator" style="display:none; text-align:center; color:red; font-size:0.8rem; margin-top:5px; animation: pulse 1s infinite;">ИДЕТ ЗАПИСЬ... ОТПУСТИТЕ ДЛЯ ОТПРАВКИ</div>
        </div>
    </div>

    <!-- Модалка: Магазин -->
    <div id="storeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>АРСЕНАЛ</h2>
                <span class="close-modal" id="closeStore">X</span>
            </div>
            <div id="storeItemsContainer">
                <!-- Контент грузится через JS -->
            </div>
        </div>
    </div>

    <!-- Модалка: Настройки (Профиль) -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>ПРОФИЛЬ</h2>
                <span class="close-modal" id="closeSettings">X</span>
            </div>
            
            <div class="profile-card">
                <h3><?php echo htmlspecialchars($_SESSION['nickname']); ?></h3>
                <p>РОЛЬ: <?php echo $_SESSION['role'] === 'hunter' ? 'ВОДА' : 'ПРЯЧУЩИЙСЯ'; ?></p>
            </div>

            <!-- Инвентарь (Активные баффы) -->
            <div class="buff-list">
                <h3 style="font-size: 0.8rem; margin-bottom: 10px; color: #aaa;">АКТИВНЫЕ БАФФЫ</h3>
                <div id="activeBuffsContainer">
                    <div style="font-size: 0.6rem; color: #555;">НЕТ БАФФОВ</div>
                </div>
            </div>

            <button class="btn btn-danger" id="btnLogout">ВЫХОД</button>
        </div>
    </div>

    <script src="/assets/js/app.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/map.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>

</body>
</html>