document.addEventListener('DOMContentLoaded', () => {
    // Service Worker (PWA)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(err => console.log('SW fail: ', err));
    }

    // --- Авторизация переключение ---
    const btnToReg = document.getElementById('linkToRegister');
    const btnToLog = document.getElementById('linkToLogin');
    if (btnToReg) btnToReg.addEventListener('click', (e) => { e.preventDefault(); document.getElementById('loginFormBox').style.display='none'; document.getElementById('registerFormBox').style.display='block'; });
    if (btnToLog) btnToLog.addEventListener('click', (e) => { e.preventDefault(); document.getElementById('loginFormBox').style.display='block'; document.getElementById('registerFormBox').style.display='none'; });

    // --- Модальные окна ---
    const btnStore = document.getElementById('btnStore');
    const btnSettings = document.getElementById('btnSettings');
    const btnChat = document.getElementById('btnChat');
    const storeModal = document.getElementById('storeModal');
    const settingsModal = document.getElementById('settingsModal');
    const chatModal = document.getElementById('chatModal');
    
    if (btnStore) btnStore.addEventListener('click', () => { if (storeModal) storeModal.style.display = 'block'; loadStoreItems(); });
    if (btnSettings) btnSettings.addEventListener('click', () => { if (settingsModal) settingsModal.style.display = 'block'; });
    if (btnChat) btnChat.addEventListener('click', () => { if (chatModal) chatModal.style.display = 'block'; loadChatMessages(); });

    // Кнопки закрытия
    const closeStore = document.getElementById('closeStore');
    const closeSettings = document.getElementById('closeSettings');
    const closeChat = document.getElementById('closeChat');
    if (closeStore) closeStore.addEventListener('click', () => storeModal.style.display = 'none');
    if (closeSettings) closeSettings.addEventListener('click', () => settingsModal.style.display = 'none');
    if (closeChat) closeChat.addEventListener('click', () => chatModal.style.display = 'none');

    const btnLogout = document.getElementById('btnLogout');
    if (btnLogout) btnLogout.addEventListener('click', () => window.location.href='/api/auth.php?action=logout');

    // Закрытие по клику вне контента
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    };


    // --- Карманный режим (Wake Lock + Черный Экран) ---
    const btnPocketMode = document.getElementById('btnPocketMode');
    const overlay = document.getElementById('pocketModeOverlay');
    const btnUnlock = document.getElementById('btnUnlock');
    let wakeLock = null;

    async function requestWakeLock() {
        if ('wakeLock' in navigator) {
            try { wakeLock = await navigator.wakeLock.request('screen'); } catch (err) {}
        }
    }
    
    function releaseWakeLock() {
        if (wakeLock !== null) { wakeLock.release().then(() => wakeLock = null); }
    }

    if (btnPocketMode) {
        btnPocketMode.addEventListener('click', () => {
            overlay.classList.add('active');
            requestWakeLock();
        });
    }
    
    if (btnUnlock) {
        btnUnlock.addEventListener('click', () => {
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.classList.remove('active');
                overlay.style.opacity = '1';
                releaseWakeLock();
            }, 800); // Совпадает с CSS transition
        });
    }

    // --- Система Toast-уведомлений ---
    window.showToast = function(message) {
        const toast = document.getElementById("toastNotification");
        if(toast) {
            toast.innerText = message;
            toast.classList.remove("show");
            void toast.offsetWidth; 
            toast.classList.add("show");
        }
    };

    // --- Инвентарь ---
    window.updateActiveBuffs = function(buffs) {
        const container = document.getElementById('activeBuffsContainer');
        if (!container) return;
        
        if (buffs && buffs.length > 0) {
            let html = '';
            buffs.forEach(buff => {
                html += `
                    <div class="buff-item">
                        <span>${buff.name}</span>
                        <span style="color:var(--success-color)">ACTIVE</span>
                    </div>
                `;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div style="font-size: 0.6rem; color: #555;">NO BUFFS</div>';
        }
    };

    // --- Инвиз Воды ---
    const btnInvis = document.getElementById('btnInvis');
    const invisText = document.getElementById('invisCooldownText');
    if (btnInvis) {
        btnInvis.addEventListener('click', () => {
            fetch('/api/game.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=activate_invis'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.showToast('ИНВИЗ АКТИВИРОВАН!');
                    btnInvis.classList.remove('pulse-anim');
                } else {
                    window.showToast(data.error || 'Ошибка');
                }
            });
        });
    }

    window.updateInvisState = function(cooldown_until, in_invis_until) {
        if (!btnInvis || !invisText) return;
        const now = new Date().getTime();
        
        if (in_invis_until) {
            const until = new Date(in_invis_until.replace(' ', 'T')).getTime();
            if (until > now) {
                btnInvis.style.background = '#2ed573'; // Зеленый
                invisText.innerText = 'АКТИВЕН';
                btnInvis.disabled = true;
                btnInvis.classList.remove('pulse-anim');
                return;
            }
        }
        
        if (cooldown_until) {
            const cd = new Date(cooldown_until.replace(' ', 'T')).getTime();
            if (cd > now) {
                btnInvis.style.background = '#888'; // Серый
                const s = Math.ceil((cd - now) / 1000);
                invisText.innerText = Math.floor(s/60) + ':' + (s%60).toString().padStart(2, '0');
                btnInvis.disabled = true;
                btnInvis.classList.remove('pulse-anim');
                return;
            }
        }

        btnInvis.style.background = 'var(--danger)'; // Красный
        invisText.innerText = 'ГОТОВ';
        btnInvis.disabled = false;
        if (!btnInvis.classList.contains('pulse-anim')) {
            btnInvis.classList.add('pulse-anim');
        }
    };

    // --- Логика магазина ---
    function loadStoreItems() {
        fetch('/api/store.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_items'
        })
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('storeItemsContainer');
            container.innerHTML = '';
            
            let hiderHtml = '<h3 style="color:#70a1ff; margin-bottom:10px; font-size: 0.9rem; border-bottom: 1px solid #333; padding-bottom: 5px;">ПРЯЧУЩИЕСЯ</h3>';
            let hunterHtml = '<h3 style="color:#ff3333; margin-top:20px; margin-bottom:10px; font-size: 0.9rem; border-bottom: 1px solid #333; padding-bottom: 5px;">ВОДА</h3>';

            if(data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    const isMyRole = (item.target_role === data.my_role || item.target_role === 'all');
                    const btnHtml = isMyRole 
                        ? `<button class="btn-buy" onclick="buyItem('${item.code}')">${item.cost} 🪙</button>` 
                        : `<button class="btn-buy" disabled style="background:#555; color:#888;">БЛОК</button>`;

                    const itemHtml = `
                        <div class="store-item" style="${!isMyRole ? 'opacity:0.6;' : ''}">
                            <div class="item-info">
                                <h4>${item.name}</h4>
                                <p>${item.description}</p>
                            </div>
                            <div class="item-buy">
                                ${btnHtml}
                            </div>
                        </div>
                    `;

                    if (item.target_role === 'hider') hiderHtml += itemHtml;
                    else if (item.target_role === 'hunter') hunterHtml += itemHtml;
                });
                
                container.innerHTML = hiderHtml + hunterHtml;
            } else {
                container.innerHTML = '<p style="text-align:center; color:#888;">Пусто. Возможно, нужно запустить patch.php</p>';
            }
        });
    }

    window.buyItem = function(code) {
        fetch('/api/store.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=buy&item_code=${code}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                showToast("Предмет успешно активирован!");
                const storeModal = document.getElementById('storeModal');
                if (storeModal) storeModal.style.display = 'none';
            } else {
                showToast(data.error || "Ошибка покупки");
            }
        });
    };

    // --- Логика Чата ---
    let chatInterval = null;
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const chatFile = document.getElementById('chatFile');

    function loadChatMessages() {
        fetch('/api/chat.php?action=get')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('chatMessages');
            if (data.error) {
                container.innerHTML = `<p style="text-align:center; color:#888;">${data.error}</p>`;
                return;
            }
            container.innerHTML = '';
            data.messages.forEach(msg => {
                const isMe = msg.is_me ? 'my-msg' : '';
                let contentHTML = '';
                if (msg.type === 'text') contentHTML = `<div class="msg-text">${msg.content}</div>`;
                else if (msg.type === 'image') contentHTML = `<img src="${msg.content}" class="msg-img">`;
                else if (msg.type === 'voice') {
                    // Поддержка видео кружков
                    if (msg.content.endsWith('.mp4') || msg.content.endsWith('.webm')) {
                        contentHTML = `<video autoplay loop muted playsinline src="${msg.content}" style="width:150px; height:150px; border-radius:50%; object-fit:cover;"></video>`;
                    } else {
                        contentHTML = `<audio controls src="${msg.content}" class="msg-audio"></audio>`;
                    }
                }

                container.innerHTML += `
                    <div class="chat-msg ${isMe}">
                        <div class="msg-author">${msg.nickname}</div>
                        ${contentHTML}
                        <div class="msg-time">${msg.time}</div>
                    </div>
                `;
            });
            container.scrollTop = container.scrollHeight;
        });
    }

    if (chatForm) {
        chatForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'send');
            
            if (chatFile.files.length > 0) {
                const file = chatFile.files[0];
                formData.append('file', file);
            } else if (chatInput.value.trim() !== '') {
                formData.append('message', chatInput.value);
            } else {
                return;
            }

            fetch('/api/chat.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    chatInput.value = '';
                    chatFile.value = '';
                    loadChatMessages();
                } else {
                    showToast(data.error);
                }
            });
        });

        chatFile.addEventListener('change', () => {
            if (chatFile.files.length > 0) {
                chatForm.dispatchEvent(new Event('submit'));
            }
        });
    }

    // --- Запись кружков (MediaRecorder) ---
    const btnRecordCircle = document.getElementById('btnRecordCircle');
    const recIndicator = document.getElementById('recordingIndicator');
    let mediaRecorder;
    let audioChunks = [];

    if (btnRecordCircle) {
        btnRecordCircle.addEventListener('pointerdown', async (e) => {
            e.preventDefault();
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];

                mediaRecorder.ondataavailable = event => {
                    if (event.data.size > 0) audioChunks.push(event.data);
                };

                mediaRecorder.onstop = () => {
                    recIndicator.style.display = 'none';
                    const blob = new Blob(audioChunks, { type: 'video/webm' });
                    
                    const formData = new FormData();
                    formData.append('action', 'send');
                    // Добавляем файл, сервер chat.php увидит 'video/' и обработает как 'voice'
                    formData.append('file', blob, 'circle.webm');

                    fetch('/api/chat.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            loadChatMessages();
                            showToast("Кружок отправлен!");
                        } else {
                            showToast(data.error || "Ошибка отправки кружка");
                        }
                    });

                    // Останавливаем потоки камеры
                    stream.getTracks().forEach(track => track.stop());
                };

                mediaRecorder.start();
                recIndicator.style.display = 'block';
            } catch (err) {
                console.error("Recording error:", err);
                showToast("Дайте разрешение на камеру и микрофон!");
            }
        });

        btnRecordCircle.addEventListener('pointerup', (e) => {
            e.preventDefault();
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
        });

        // Если палец ушел с кнопки
        btnRecordCircle.addEventListener('pointerleave', (e) => {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
        });
    }

    // Автообновление чата, если он открыт
    setInterval(() => {
        const chatModal = document.getElementById('chatModal');
        if (chatModal && chatModal.style.display === 'block') {
            loadChatMessages();
        }
    }, 3000);
});