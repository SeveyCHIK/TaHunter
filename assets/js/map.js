let gameMap = null;
let playerMarkers = {};
let currentLat = null;
let currentLng = null;
let lastBroadcast = null;

// Координаты ТЦ МЕГА Уфа (lat, lon) для Yandex Maps
const MEGA_UFA_CENTER = [54.674936, 55.933405];

document.addEventListener('DOMContentLoaded', () => {
    if (typeof ymaps !== 'undefined') {
        ymaps.ready(initYandexMap);
    }
});

function initYandexMap() {
    const container = document.getElementById('mapContainer');
    if (!container) return;

    gameMap = new ymaps.Map("mapContainer", {
        center: MEGA_UFA_CENTER,
        zoom: 19, // Максимальный зум для отображения контуров помещений ТЦ в Яндекс Картах
        controls: [] // Отключаем лишние контролы
    });

    // Добавляем геолокацию нативного браузера
    if ('geolocation' in navigator) {
        navigator.geolocation.watchPosition(
            (position) => {
                currentLat = position.coords.latitude;
                currentLng = position.coords.longitude;
            },
            (error) => {
                console.error("GPS Error:", error);
                if(typeof window.showToast === 'function') {
                    window.showToast("GPS: Включите геолокацию!");
                }
            },
            {
                enableHighAccuracy: true,
                maximumAge: 5000,
                timeout: 5000
            }
        );
    }

    // Запускаем игровой цикл
    setInterval(gameTick, 5000); // 5 секунд для динамики
    gameTick();
}

function gameTick() {
    const formData = new FormData();
    formData.append('action', 'game_tick');
    if (currentLat !== null && currentLng !== null) {
        formData.append('lat', currentLat);
        formData.append('lng', currentLng);
    }

    fetch('/api/game.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.coins !== undefined) {
            const cb = document.getElementById('coinBalance');
            if (cb) cb.innerText = data.coins;
        }

        const statusEl = document.getElementById('gameStatusIndicator');
        if (statusEl) {
            if (data.is_active) {
                statusEl.style.display = 'none';
            } else {
                statusEl.style.display = 'block';
                statusEl.innerText = 'ОЖИДАНИЕ АДМИНА...';
            }
        }

        if (data.broadcast && data.broadcast !== lastBroadcast) {
            lastBroadcast = data.broadcast;
            if (typeof window.showToast === 'function') {
                window.showToast("ВНИМАНИЕ: " + data.broadcast);
            }
        }

        updateMapMarkers(data.players || []);
        
        if (typeof window.updateActiveBuffs === 'function') {
            window.updateActiveBuffs(data.my_buffs || []);
        }

        if (typeof window.updateInvisState === 'function') {
            window.updateInvisState(data.invis_cooldown_until, data.in_invis_until);
        }
    })
    .catch(err => console.error("Game Tick Error:", err));
}

function updateMapMarkers(players) {
    if (!gameMap) return;

    const currentIds = new Set(players.map(p => p.id));

    // Удаляем маркеров, которых больше нет
    for (let id in playerMarkers) {
        if (id === 'self') continue;
        if (!currentIds.has(parseInt(id))) {
            gameMap.geoObjects.remove(playerMarkers[id]);
            delete playerMarkers[id];
        }
    }

    // Добавляем или обновляем
    players.forEach(player => {
        const coords = [player.lat, player.lng];
        
        if (playerMarkers[player.id]) {
            // Плавное перемещение (эмулируется через CSS transition внутри HTML Layout)
            playerMarkers[player.id].geometry.setCoordinates(coords);
        } else {
            const iconClass = player.role === 'hunter' ? 'marker-hunter' : 'marker-hider';
            const emoji = player.role === 'hunter' ? '🐺' : '🏃';
            
            const customLayout = ymaps.templateLayoutFactory.createClass(
                '<div class="custom-marker ' + iconClass + '"><div class="marker-name">' + player.nickname + '</div>' + emoji + '</div>'
            );

            const marker = new ymaps.Placemark(coords, {}, {
                iconLayout: customLayout,
                iconShape: { type: 'Rectangle', coordinates: [[-15, -15], [15, 15]] }
            });

            gameMap.geoObjects.add(marker);
            playerMarkers[player.id] = marker;
        }
    });

    // Обновляем свою позицию
    if (currentLat && currentLng) {
        const coords = [currentLat, currentLng];
        if (playerMarkers['self']) {
            playerMarkers['self'].geometry.setCoordinates(coords);
        } else {
            const selfLayout = ymaps.templateLayoutFactory.createClass(
                '<div class="custom-marker marker-self"><div class="marker-name">ВЫ</div>📍</div>'
            );
            const selfPlacemark = new ymaps.Placemark(coords, {}, {
                iconLayout: selfLayout,
                iconShape: { type: 'Rectangle', coordinates: [[-15, -15], [15, 15]] }
            });
            gameMap.geoObjects.add(selfPlacemark);
            playerMarkers['self'] = selfPlacemark;
            
            // Центрируем карту на себя при первом появлении
            gameMap.setCenter(coords, 19);
        }
    }
}