<?php
// ============================================
// OBTENER NOTIFICACIONES PARA DROPDOWN (AJAX)
// ============================================

require_once 'config.php';
require_once 'notificaciones_helper.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Obtener últimas 5 notificaciones
    $stmt = $conn->prepare("
        SELECT * FROM notificaciones 
        WHERE usuario_id = ? 
        ORDER BY fecha_creacion DESC 
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $notificaciones = $stmt->fetchAll();
    
    $html = '';
    
    if (empty($notificaciones)) {
        $html .= '<div class="notification-empty">';
        $html .= '<i class="fa-regular fa-bell-slash"></i>';
        $html .= '<p>No hay notificaciones</p>';
        $html .= '</div>';
    } else {
        foreach ($notificaciones as $notif) {
            // Calcular tiempo
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
            
            // Icono según tipo
            $icono = 'fa-bell';
            switch ($notif['tipo']) {
                case 'vacaciones': $icono = 'fa-umbrella-beach'; break;
                case 'turno': $icono = 'fa-calendar'; break;
                case 'ausencia': $icono = 'fa-clock'; break;
                case 'confirmacion': $icono = 'fa-circle-check'; break;
                case 'sistema': $icono = 'fa-gear'; break;
            }
            
            $html .= '<div class="notification-item ' . (!$notif['leida'] ? 'unread' : '') . '" data-id="' . $notif['id'] . '" onclick="window.location.href=\'' . htmlspecialchars($notif['enlace'] ?? 'notificaciones.php') . '\'">';
            $html .= '<div class="notification-item-icon">';
            $html .= '<i class="fa-regular ' . $icono . '"></i>';
            $html .= '</div>';
            $html .= '<div class="notification-item-content">';
            $html .= '<div class="notification-item-title">' . htmlspecialchars($notif['titulo']) . '</div>';
            $html .= '<div class="notification-item-time">' . $tiempo . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>