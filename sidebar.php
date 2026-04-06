<?php
// ============================================
// SIDEBAR - SIN SVG
// ============================================

require_once 'config.php';

if (!isset($_SESSION['user_role'])) {
    die('Acceso no autorizado');
}

$current_page = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['user_role'];
?>

<style>
.sidebar {
    width: 260px;
    background: linear-gradient(180deg, #1a1f2e 0%, #0f1420 100%);
    color: white;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
    transition: width 0.3s ease;
    overflow-y: auto;
}

.sidebar.collapsed {
    width: 80px;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 700;
    color: white;
}

.logo i {
    font-size: 24px;
    color: #3b82f6;
}

.logo span {
    color: #3b82f6;
}

.sidebar.collapsed .logo span,
.sidebar.collapsed .nav-label {
    display: none;
}

.toggle-btn {
    width: 32px;
    height: 32px;
    background: rgba(255,255,255,0.1);
    border: none;
    border-radius: 8px;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toggle-btn:hover {
    background: #3b82f6;
}

.sidebar-nav {
    padding: 20px 0;
}

.nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin: 4px 12px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: #94a3b8;
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.2s;
}

.nav-link:hover {
    background: rgba(59, 130, 246, 0.15);
    color: white;
}

.nav-link.active {
    background: #3b82f6;
    color: white;
}

.nav-link i {
    font-size: 20px;
    width: 20px;
}

.nav-label {
    font-size: 14px;
    font-weight: 500;
}

.nav-divider {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 15px 12px;
}

.badge {
    background: #ef4444;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 30px;
    margin-left: auto;
}
</style>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-clock"></i>
            <div>Hormi<span>Clock</span></div>
        </div>
        <button class="toggle-btn" id="toggleSidebar">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <!-- DASHBOARD -->
            <li class="nav-item">
                <a href="<?= $userRole === 'admin' ? 'admin_page.php' : 'user_page.php' ?>" 
                   class="nav-link <?= $current_page == 'admin_page.php' || $current_page == 'user_page.php' ? 'active' : '' ?>">
                    <i class="fas fa-th-large"></i>
                    <span class="nav-label">Dashboard</span>
                </a>
            </li>

            <!-- TURNOS -->
            <li class="nav-item">
                <a href="calendar.php?view=turnos" 
                   class="nav-link <?= $current_page == 'calendar.php' && isset($_GET['view']) && $_GET['view'] == 'turnos' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span class="nav-label">Turnos</span>
                </a>
            </li>

            <!-- EVENTOS -->
            <li class="nav-item">
                <a href="calendar.php" 
                   class="nav-link <?= $current_page == 'calendar.php' && !isset($_GET['view']) ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span class="nav-label">Eventos</span>
                </a>
            </li>

            <!-- NOTIFICACIONES -->
            <li class="nav-item">
                <a href="notificaciones.php" 
                   class="nav-link <?= $current_page == 'notificaciones.php' ? 'active' : '' ?>">
                    <i class="fas fa-bell"></i>
                    <span class="nav-label">Notificaciones</span>
                    <span class="badge" id="notifBadge" style="display: none;">0</span>
                </a>
            </li>

            <!-- ACTIVIDAD -->
            <li class="nav-item">
                <a href="activity.php" 
                   class="nav-link <?= $current_page == 'activity.php' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i>
                    <span class="nav-label">Actividad</span>
                </a>
            </li>

            <div class="nav-divider"></div>

            <!-- PERFIL -->
            <li class="nav-item">
                <a href="profile.php" 
                   class="nav-link <?= $current_page == 'profile.php' ? 'active' : '' ?>">
                    <i class="fas fa-user"></i>
                    <span class="nav-label">Mi Perfil</span>
                </a>
            </li>

            <?php if ($userRole === 'admin'): ?>
            <div class="nav-divider"></div>

            <!-- ADMIN - Usuarios -->
            <li class="nav-item">
                <a href="users_list.php" 
                   class="nav-link <?= $current_page == 'users_list.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    <span class="nav-label">Usuarios</span>
                </a>
            </li>

            <!-- ADMIN - Departamentos -->
            <li class="nav-item">
                <a href="departments.php" 
                   class="nav-link <?= $current_page == 'departments.php' ? 'active' : '' ?>">
                    <i class="fas fa-building"></i>
                    <span class="nav-label">Departamentos</span>
                </a>
            </li>

            <!-- ADMIN - Puestos -->
            <li class="nav-item">
                <a href="positions.php" 
                   class="nav-link <?= $current_page == 'positions.php' ? 'active' : '' ?>">
                    <i class="fas fa-briefcase"></i>
                    <span class="nav-label">Puestos</span>
                </a>
            </li>

            <!-- ADMIN - Ausencias -->
            <li class="nav-item">
                <a href="ausencias.php" 
                   class="nav-link <?= $current_page == 'ausencias.php' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i>
                    <span class="nav-label">Ausencias</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</aside>

<script>
document.getElementById('toggleSidebar')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    localStorage.setItem('sidebarCollapsed', document.getElementById('sidebar').classList.contains('collapsed'));
});

if (localStorage.getItem('sidebarCollapsed') === 'true') {
    document.getElementById('sidebar')?.classList.add('collapsed');
}

fetch('verificar_notificaciones.php')
    .then(r => r.json())
    .then(d => {
        const badge = document.getElementById('notifBadge');
        if (d.nuevas > 0) {
            badge.textContent = d.nuevas;
            badge.style.display = 'inline';
        }
    });
</script>