<?php
// ============================================
// HEADER PRINCIPAL - CON PERFIL MEJORADO Y ÍCONOS CORREGIDOS
// ============================================

if (!isset($_SESSION['name'])) return;

require_once 'config.php';

$userName = htmlspecialchars($_SESSION['name'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$userEmail = $_SESSION['email'] ?? '';
$userRole = $_SESSION['user_role'] ?? 'user';
$userId = $_SESSION['user_id'] ?? 0;

// Obtener foto de perfil o iniciales
$profile_img = $_SESSION['profile_img'] ?? null;
$iniciales = '';

if (!$profile_img || $profile_img === 'default.svg' || !file_exists($profile_img)) {
    $partes_nombre = explode(' ', trim($userName));
    foreach ($partes_nombre as $parte) {
        if (!empty($parte)) {
            $iniciales .= strtoupper(substr($parte, 0, 1));
        }
    }
    $iniciales = substr($iniciales, 0, 2);
}

// Obtener username
$username = '';
try {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    $username = $result['username'] ?? explode('@', $userEmail)[0];
} catch (Exception $e) {
    $username = explode('@', $userEmail)[0];
}

// ============================================
// NOTIFICACIONES
// ============================================
$notificaciones_dropdown = [];
$notificaciones_no_leidas = 0;
$ultima_notificacion_id = 0;

try {
    $stmt = $conn->prepare("
        SELECT * FROM notificaciones 
        WHERE usuario_id = ? 
        ORDER BY fecha_creacion DESC 
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $notificaciones_dropdown = $stmt->fetchAll();
    
    if (!empty($notificaciones_dropdown)) {
        $ultima_notificacion_id = $notificaciones_dropdown[0]['id'];
    }
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM notificaciones 
        WHERE usuario_id = ? AND leida = FALSE
    ");
    $stmt->execute([$userId]);
    $notificaciones_no_leidas = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $notificaciones_dropdown = [];
    $notificaciones_no_leidas = 0;
}

// ============================================
// UBICACIÓN Y HORA
// ============================================
$ip_real = getRealIP();

if ($ip_real == '::1' || $ip_real == '127.0.0.1') {
    $ciudad = 'Caracas';
    $pais = 'Venezuela';
    $zona_horaria = 'America/Caracas';
} else {
    $opts = ['http' => ['timeout' => 3]];
    $context = stream_context_create($opts);
    $location_data = @file_get_contents("http://ipapi.co/{$ip_real}/json/", false, $context);
    
    if ($location_data) {
        $location = json_decode($location_data, true);
        $ciudad = $location['city'] ?? 'Caracas';
        $pais = $location['country_name'] ?? 'Venezuela';
        $zona_horaria = $location['timezone'] ?? 'America/Caracas';
    } else {
        $ciudad = 'Caracas';
        $pais = 'Venezuela';
        $zona_horaria = 'America/Caracas';
    }
}

$tema = $_COOKIE['tema'] ?? 'light';
?>
<style>
.header-bar {
    position: fixed;
    top: 0;
    right: 0;
    left: 260px;
    height: 70px;
    background: white;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0 30px;
    z-index: 999;
    transition: left 0.3s ease;
}

body.dark-mode .header-bar {
    background: #1e293b;
    border-bottom-color: #334155;
}

.sidebar.collapsed ~ .header-bar {
    left: 80px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

.location-time {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    background: #f8fafc;
    border-radius: 30px;
    font-size: 13px;
    color: #1e293b;
}

body.dark-mode .location-time {
    background: #0f172a;
    color: #f1f5f9;
}

.location-time i {
    color: #3b82f6;
}

.separator {
    width: 1px;
    height: 20px;
    background: #e2e8f0;
    margin: 0 8px;
}

body.dark-mode .separator {
    background: #334155;
}

.notification-container {
    position: relative;
}

.notification-btn {
    position: relative;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #f8fafc;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #1e293b;
    font-size: 18px;
    transition: all 0.2s;
}

body.dark-mode .notification-btn {
    background: #0f172a;
    color: #f1f5f9;
}

.notification-btn:hover {
    background: #e2e8f0;
}

@keyframes shake {
    0% { transform: rotate(0deg); }
    25% { transform: rotate(10deg); }
    50% { transform: rotate(-10deg); }
    75% { transform: rotate(5deg); }
    100% { transform: rotate(0deg); }
}

.notification-btn.has-new {
    animation: shake 0.5s ease-in-out;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    background: #ef4444;
    border-radius: 30px;
    color: white;
    font-size: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}

body.dark-mode .notification-badge {
    border-color: #1e293b;
}

.notification-dropdown {
    position: absolute;
    top: 50px;
    right: 0;
    width: 350px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    z-index: 1000;
    max-height: 500px;
    overflow-y: auto;
}

body.dark-mode .notification-dropdown {
    background: #1e293b;
    border-color: #334155;
}

.notification-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
    position: sticky;
    top: 0;
    background: white;
    border-radius: 16px 16px 0 0;
    z-index: 2;
}

body.dark-mode .notification-header {
    background: #1e293b;
    border-bottom-color: #334155;
}

.notification-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}

body.dark-mode .notification-header h3 {
    color: #f1f5f9;
}

.notification-header a {
    color: #3b82f6;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}

.notification-header a:hover {
    text-decoration: underline;
}

.notification-list {
    padding: 8px;
}

.notification-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.notification-item:hover {
    background: #f8fafc;
}

body.dark-mode .notification-item:hover {
    background: #0f172a;
}

.notification-item.unread {
    background: #eff6ff;
}

body.dark-mode .notification-item.unread {
    background: #1e3a5f;
}

.notification-item-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
    font-size: 18px;
    flex-shrink: 0;
}

body.dark-mode .notification-item-icon {
    background: #0f172a;
}

.notification-item-content {
    flex: 1;
}

.notification-item-title {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 4px;
}

body.dark-mode .notification-item-title {
    color: #f1f5f9;
}

.notification-item-time {
    font-size: 12px;
    color: #64748b;
}

.notification-empty {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
}

.notification-empty i {
    font-size: 40px;
    margin-bottom: 10px;
    opacity: 0.5;
}

.theme-btn {
    position: relative;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #f8fafc;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #1e293b;
    font-size: 18px;
    transition: all 0.2s;
}

body.dark-mode .theme-btn {
    background: #0f172a;
    color: #f1f5f9;
}

.theme-btn:hover {
    background: #e2e8f0;
}

.theme-tooltip {
    position: absolute;
    bottom: -35px;
    left: 50%;
    transform: translateX(-50%);
    background: #1e293b;
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s;
    pointer-events: none;
    z-index: 1001;
}

body.dark-mode .theme-tooltip {
    background: #f8fafc;
    color: #1e293b;
}

.theme-btn:hover .theme-tooltip {
    opacity: 1;
    visibility: visible;
    bottom: -40px;
}

/* ========== PERFIL MEJORADO ========== */
.profile-container {
    position: relative;
}

.profile-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 4px 6px 4px 16px;
    background: #f8fafc;
    border-radius: 40px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

body.dark-mode .profile-btn {
    background: #0f172a;
}

.profile-btn:hover {
    background: #e2e8f0;
    transform: translateY(-1px);
}

.profile-info {
    text-align: right;
}

.profile-username {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    display: block;
    line-height: 1.3;
}

body.dark-mode .profile-username {
    color: #f1f5f9;
}

.profile-role {
    font-size: 10px;
    color: #3b82f6;
    display: block;
    font-weight: 500;
    margin-top: 2px;
}

.profile-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
    overflow: hidden;
    flex-shrink: 0;
}

.profile-avatar.has-image {
    background: transparent;
}

.profile-avatar.no-image {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    color: white;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-dropdown {
    position: absolute;
    top: 55px;
    right: 0;
    width: 240px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    border: 1px solid #e2e8f0;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    z-index: 1000;
    overflow: hidden;
}

body.dark-mode .profile-dropdown {
    background: #1e293b;
    border-color: #334155;
}

.profile-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 18px;
    color: #1e293b;
    text-decoration: none;
    transition: all 0.2s;
    font-size: 14px;
    font-weight: 500;
}

body.dark-mode .dropdown-item {
    color: #f1f5f9;
}

.dropdown-item i {
    width: 20px;
    font-size: 16px;
    color: #64748b;
    transition: all 0.2s;
}

body.dark-mode .dropdown-item i {
    color: #94a3b8;
}

.dropdown-item:hover {
    background: #f8fafc;
}

body.dark-mode .dropdown-item:hover {
    background: #0f172a;
}

.dropdown-item:hover i {
    color: #3b82f6;
}

.dropdown-divider {
    height: 1px;
    background: #e2e8f0;
    margin: 8px 0;
}

body.dark-mode .dropdown-divider {
    background: #334155;
}

.logout-item {
    color: #ef4444 !important;
}

.logout-item i {
    color: #ef4444 !important;
}

.logout-item:hover {
    background: rgba(239, 68, 68, 0.1) !important;
}

@media (max-width: 1024px) {
    .header-bar {
        left: 0;
    }
}

@media (max-width: 768px) {
    .header-bar {
        padding: 0 15px;
    }
    
    .location-time span:not(.time) {
        display: none;
    }
    
    .profile-info {
        display: none;
    }
    
    .profile-btn {
        padding: 4px;
    }
    
    .notification-dropdown {
        width: 300px;
        right: -50px;
    }
}

@media (max-width: 480px) {
    .location-time {
        display: none;
    }
}
</style>

<header class="header-bar">
    <div class="header-actions">
        <!-- Ubicación y hora -->
        <div class="location-time">
            <i class="fas fa-location-dot"></i>
            <span><?= $ciudad ?>, <?= $pais ?></span>
            <span class="separator"></span>
            <i class="far fa-clock"></i>
            <span class="time" id="header-time"></span>
            <span class="time-seconds" id="header-seconds"></span>
        </div>

        <!-- NOTIFICACIONES -->
        <div class="notification-container">
            <button class="notification-btn <?= $notificaciones_no_leidas > 0 ? 'has-new' : '' ?>" id="notificationBtn">
                <i class="far fa-bell"></i>
                <span class="notification-badge" id="notificationBadge" style="<?= $notificaciones_no_leidas > 0 ? '' : 'display: none;' ?>">
                    <?= $notificaciones_no_leidas ?>
                </span>
            </button>

            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h3>Notificaciones</h3>
                    <a href="notificaciones.php">Ver todas</a>
                </div>
                
                <div class="notification-list" id="notificationList">
                    <?php if (empty($notificaciones_dropdown)): ?>
                        <div class="notification-empty">
                            <i class="far fa-bell-slash"></i>
                            <p>No hay notificaciones</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notificaciones_dropdown as $notif): 
                            $fecha = new DateTime($notif['fecha_creacion']);
                            $ahora = new DateTime();
                            $diff = $ahora->diff($fecha);
                            
                            if ($diff->days == 0) {
                                if ($diff->h == 0) {
                                    $tiempo = "hace {$diff->i} min";
                                } else {
                                    $tiempo = "hace {$diff->h} h";
                                }
                            } elseif ($diff->days == 1) {
                                $tiempo = "ayer";
                            } else {
                                $tiempo = "hace {$diff->days} días";
                            }
                            
                            $icono = 'fa-bell';
                            switch ($notif['tipo']) {
                                case 'vacaciones': $icono = 'fa-umbrella-beach'; break;
                                case 'turno': $icono = 'fa-calendar'; break;
                                case 'ausencia': $icono = 'fa-clock'; break;
                                case 'confirmacion': $icono = 'fa-check-circle'; break;
                                case 'sistema': $icono = 'fa-cog'; break;
                            }
                        ?>
                        <div class="notification-item <?= !$notif['leida'] ? 'unread' : '' ?>" 
                             data-id="<?= $notif['id'] ?>"
                             onclick="window.location.href='<?= htmlspecialchars($notif['enlace'] ?? 'notificaciones.php') ?>'">
                            <div class="notification-item-icon">
                                <i class="fas <?= $icono ?>"></i>
                            </div>
                            <div class="notification-item-content">
                                <div class="notification-item-title"><?= htmlspecialchars($notif['titulo']) ?></div>
                                <div class="notification-item-time"><?= $tiempo ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Botón Dark/Light -->
        <button class="theme-btn" onclick="toggleTheme()">
            <i class="<?= $tema === 'dark' ? 'fas fa-sun' : 'fas fa-moon' ?>"></i>
            <span class="theme-tooltip"><?= $tema === 'dark' ? 'Modo Claro' : 'Modo Oscuro' ?></span>
        </button>

        <!-- Perfil MEJORADO CON ÍCONOS CORREGIDOS -->
        <div class="profile-container">
            <button class="profile-btn" id="profileBtn">
                <div class="profile-info">
                    <span class="profile-username">@<?= htmlspecialchars($username) ?></span>
                    <span class="profile-role"><?= $userRole === 'admin' ? 'Administrador' : 'Empleado' ?></span>
                </div>
                <div class="profile-avatar <?= $profile_img && $profile_img !== 'default.svg' && file_exists($profile_img) ? 'has-image' : 'no-image' ?>">
                    <?php if ($profile_img && $profile_img !== 'default.svg' && file_exists($profile_img)): ?>
                        <img src="<?= htmlspecialchars($profile_img) ?>" alt="Perfil">
                    <?php else: ?>
                        <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                </div>
            </button>

            <div class="profile-dropdown" id="profileDropdown">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    <span>Mi Perfil</span>
                </a>
                <a href="activity.php" class="dropdown-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Actividad</span>
                </a>
                <?php if ($userRole === 'admin'): ?>
                <a href="admin_page.php" class="dropdown-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Panel de Administración</span>
                </a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item logout-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar sesión</span>
                </a>
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let ultimoIdNotificacion = <?= $ultima_notificacion_id ?>;
    let notificacionesNoLeidas = <?= $notificaciones_no_leidas ?>;
    
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    
    if (profileBtn) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
    }
    
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationBadge = document.getElementById('notificationBadge');
    const notificationList = document.getElementById('notificationList');
    
    if (notificationBtn && notificationDropdown) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            notificationBtn.classList.remove('has-new');
            if (profileDropdown) profileDropdown.classList.remove('show');
        });
        
        notificationDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    function verificarNuevasNotificaciones() {
        fetch('verificar_notificaciones.php?ultimo_id=' + ultimoIdNotificacion)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.nuevas > 0) {
                    notificacionesNoLeidas += data.nuevas;
                    notificationBadge.textContent = notificacionesNoLeidas;
                    notificationBadge.style.display = 'flex';
                    notificationBtn.classList.add('has-new');
                    if (data.ultimo_id > ultimoIdNotificacion) {
                        ultimoIdNotificacion = data.ultimo_id;
                    }
                    if (notificationDropdown.classList.contains('show')) {
                        recargarNotificaciones();
                    }
                }
            })
            .catch(error => console.error('Error verificando notificaciones:', error));
    }
    
    function recargarNotificaciones() {
        fetch('get_notificaciones_dropdown.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notificationList.innerHTML = data.html;
                }
            })
            .catch(error => console.error('Error recargando notificaciones:', error));
    }
    
    document.addEventListener('click', function(e) {
        const notificacionItem = e.target.closest('.notification-item');
        if (notificacionItem) {
            const notifId = notificacionItem.dataset.id;
            if (notifId) {
                fetch('marcar_notificacion.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: notifId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        notificacionesNoLeidas--;
                        if (notificacionesNoLeidas <= 0) {
                            notificationBadge.style.display = 'none';
                            notificationBtn.classList.remove('has-new');
                        } else {
                            notificationBadge.textContent = notificacionesNoLeidas;
                        }
                        notificacionItem.classList.remove('unread');
                    }
                })
                .catch(error => console.error('Error marcando notificación:', error));
            }
        }
    });
    
    document.addEventListener('click', function() {
        if (profileDropdown) profileDropdown.classList.remove('show');
        if (notificationDropdown) notificationDropdown.classList.remove('show');
    });
    
    setInterval(verificarNuevasNotificaciones, 10000);
});

function toggleTheme() {
    const body = document.body;
    body.classList.toggle('dark-mode');
    
    const icon = document.querySelector('.theme-btn i');
    const tooltip = document.querySelector('.theme-tooltip');
    
    if (body.classList.contains('dark-mode')) {
        icon.className = 'fas fa-sun';
        tooltip.textContent = 'Modo Claro';
        document.cookie = 'tema=dark; path=/';
    } else {
        icon.className = 'fas fa-moon';
        tooltip.textContent = 'Modo Oscuro';
        document.cookie = 'tema=light; path=/';
    }
}

function actualizarHora() {
    const ahora = new Date();
    const hora = ahora.toLocaleTimeString('es-ES', { 
        hour: '2-digit', 
        minute: '2-digit', 
        timeZone: '<?= $zona_horaria ?>' 
    });
    const segundos = ahora.toLocaleTimeString('es-ES', { 
        second: '2-digit', 
        timeZone: '<?= $zona_horaria ?>' 
    });
    
    document.getElementById('header-time').textContent = hora;
    document.getElementById('header-seconds').textContent = segundos;
}

actualizarHora();
setInterval(actualizarHora, 1000);
</script>
<script src="session-timeout.js"></script>