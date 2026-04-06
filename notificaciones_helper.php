<?php
// ============================================
// NOTIFICACIONES HELPER - VERSIÓN COMPLETA
// ============================================

require_once 'config.php';

class Notificaciones {
    
    /**
     * Crea la tabla de notificaciones si no existe
     */
    public static function crearTabla() {
        global $conn;
        
        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS notificaciones (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NOT NULL,
                    tipo ENUM('vacaciones', 'turno', 'ausencia', 'confirmacion', 'sistema') NOT NULL,
                    titulo VARCHAR(255) NOT NULL,
                    mensaje TEXT,
                    enlace VARCHAR(255),
                    leida BOOLEAN DEFAULT FALSE,
                    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    fecha_lectura TIMESTAMP NULL,
                    FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            return true;
        } catch (Exception $e) {
            error_log("Error creando tabla notificaciones: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crea una notificación en la base de datos
     */
    public static function crear($usuario_id, $tipo, $titulo, $mensaje, $enlace = null) {
        global $conn;
        
        try {
            // Asegurar que la tabla existe
            self::crearTabla();
            
            $stmt = $conn->prepare("
                INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([$usuario_id, $tipo, $titulo, $mensaje, $enlace]);
        } catch (Exception $e) {
            error_log("Error creando notificación: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * NOTIFICACIÓN 1: Nuevo turno asignado
     */
    public static function nuevoTurno($empleado_id, $fecha_turno, $hora_inicio, $hora_fin) {
        $titulo = "📅 Nuevo turno asignado";
        $mensaje = "Se te ha asignado un turno el " . date('d/m/Y', strtotime($fecha_turno)) . 
                   " de {$hora_inicio} a {$hora_fin}";
        
        return self::crear($empleado_id, 'turno', $titulo, $mensaje, 'calendar.php');
    }
    
    /**
     * NOTIFICACIÓN 2: Pocos días de vacaciones
     */
    public static function vacacionesRestantes($empleado_id, $dias_restantes) {
        if ($dias_restantes <= 5 && $dias_restantes > 0) {
            $titulo = "🏖️ Quedan pocos días de vacaciones";
            $mensaje = "Te quedan {$dias_restantes} días de vacaciones disponibles este año";
            
            return self::crear($empleado_id, 'vacaciones', $titulo, $mensaje, 'ausencias.php');
        }
        return false;
    }
    
    /**
     * NOTIFICACIÓN 3: Empleado confirmó turno (para admin)
     */
    public static function turnoConfirmado($admin_id, $empleado_nombre, $fecha_turno) {
        $titulo = "✅ Turno confirmado";
        $mensaje = "{$empleado_nombre} ha confirmado su turno del " . date('d/m/Y', strtotime($fecha_turno));
        
        return self::crear($admin_id, 'confirmacion', $titulo, $mensaje, 'calendar.php');
    }
    
    /**
     * NOTIFICACIÓN 4: Nueva solicitud de ausencia (para admin)
     */
    public static function nuevaSolicitudAusencia($admin_id, $empleado_nombre, $tipo, $fecha_inicio, $fecha_fin) {
        $titulo = "📋 Nueva solicitud de " . $tipo;
        $mensaje = "{$empleado_nombre} solicita {$tipo} del " . 
                   date('d/m/Y', strtotime($fecha_inicio)) . " al " . 
                   date('d/m/Y', strtotime($fecha_fin));
        
        return self::crear($admin_id, 'ausencia', $titulo, $mensaje, 'ausencias.php');
    }
    
    /**
     * NOTIFICACIÓN 5: Ausencia aprobada/rechazada (para empleado)
     */
    public static function estadoAusencia($empleado_id, $tipo, $estado, $fecha_inicio, $fecha_fin) {
        $estado_texto = $estado == 'aprobado' ? 'aprobada' : 'rechazada';
        $titulo = $estado == 'aprobado' ? "✅ Solicitud aprobada" : "❌ Solicitud rechazada";
        $mensaje = "Tu solicitud de {$tipo} del " . date('d/m/Y', strtotime($fecha_inicio)) . 
                   " al " . date('d/m/Y', strtotime($fecha_fin)) . " ha sido {$estado_texto}";
        
        return self::crear($empleado_id, 'ausencia', $titulo, $mensaje, 'ausencias.php');
    }
    
    /**
     * NOTIFICACIÓN 6: Recordatorio de turno próximo (24h antes)
     */
    public static function recordatorioTurno($empleado_id, $fecha_turno, $hora_inicio) {
        $fecha_formateada = date('d/m/Y', strtotime($fecha_turno));
        $dia_semana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'][date('w', strtotime($fecha_turno))];
        
        $titulo = "⏰ Recordatorio: Turno mañana";
        $mensaje = "Tienes un turno el {$dia_semana} {$fecha_formateada} a las {$hora_inicio}";
        
        return self::crear($empleado_id, 'turno', $titulo, $mensaje, 'calendar.php');
    }
    
    /**
     * NOTIFICACIÓN 7: Sistema/mantenimiento
     */
    public static function sistema($usuario_id, $titulo, $mensaje, $enlace = null) {
        return self::crear($usuario_id, 'sistema', $titulo, $mensaje, $enlace);
    }
    
    /**
     * NOTIFICACIÓN 8: Asignado a nuevo departamento
     */
    public static function nuevoDepartamento($usuario_id, $nombre_departamento, $nombre_usuario) {
        $titulo = "🏢 Asignado a nuevo departamento";
        $mensaje = "Has sido asignado al departamento de {$nombre_departamento}";
        
        return self::crear($usuario_id, 'sistema', $titulo, $mensaje, 'profile.php?destacar=departamento');
    }
    
    /**
     * NOTIFICACIÓN 9: Aumento de salario
     */
    public static function aumentoSalario($usuario_id, $salario_anterior, $salario_nuevo) {
        $titulo = "💰 Aumento de salario";
        $mensaje = "Tu salario ha sido actualizado de $" . number_format($salario_anterior, 2) . 
                   " a $" . number_format($salario_nuevo, 2);
        
        return self::crear($usuario_id, 'sistema', $titulo, $mensaje, 'profile.php');
    }
    
    /**
     * NOTIFICACIÓN 10: Cambio de puesto
     */
    public static function cambioPuesto($usuario_id, $puesto_anterior, $puesto_nuevo) {
        $titulo = "🔄 Cambio de puesto";
        $mensaje = "Has sido asignado al puesto de {$puesto_nuevo} (anterior: {$puesto_anterior})";
        
        return self::crear($usuario_id, 'sistema', $titulo, $mensaje, 'profile.php');
    }
    
    /**
     * Verificar recordatorios de turnos (para ejecutar con CRON)
     */
    public static function verificarRecordatoriosTurnos() {
        global $conn;
        
        // Asegurar que la tabla existe
        self::crearTabla();
        
        $manana_inicio = date('Y-m-d H:i:s', strtotime('+23 hours'));
        $manana_fin = date('Y-m-d H:i:s', strtotime('+25 hours'));
        
        try {
            $stmt = $conn->prepare("
                SELECT e.*, u.id as usuario_id, u.name as empleado_nombre
                FROM events e
                JOIN users u ON e.user_id = u.id
                WHERE e.start_date BETWEEN ? AND ?
                AND NOT EXISTS (
                    SELECT 1 FROM notificaciones n 
                    WHERE n.usuario_id = u.id 
                    AND n.tipo = 'turno' 
                    AND n.titulo LIKE '%Recordatorio%'
                    AND DATE(n.fecha_creacion) = CURDATE()
                )
            ");
            $stmt->execute([$manana_inicio, $manana_fin]);
            $turnos = $stmt->fetchAll();
            
            foreach ($turnos as $turno) {
                self::recordatorioTurno(
                    $turno['usuario_id'],
                    $turno['start_date'],
                    date('H:i', strtotime($turno['start_date']))
                );
            }
        } catch (Exception $e) {
            error_log("Error verificando recordatorios: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar días de vacaciones bajos (ejecutar diariamente)
     */
    public static function verificarVacacionesBajas() {
        global $conn;
        
        // Asegurar que la tabla existe
        self::crearTabla();
        
        $dias_totales = 15;
        
        try {
            $stmt = $conn->prepare("
                SELECT u.id, 
                       COALESCE(SUM(DATEDIFF(a.fecha_fin, a.fecha_inicio) + 1), 0) as dias_tomados
                FROM users u
                LEFT JOIN ausencias a ON u.id = a.user_id 
                    AND a.tipo = 'vacaciones' 
                    AND a.estado = 'aprobado'
                    AND YEAR(a.fecha_inicio) = YEAR(CURDATE())
                WHERE u.role = 'user'
                GROUP BY u.id
            ");
            $stmt->execute();
            $usuarios = $stmt->fetchAll();
            
            foreach ($usuarios as $usuario) {
                $dias_restantes = $dias_totales - $usuario['dias_tomados'];
                
                if ($dias_restantes <= 5 && $dias_restantes > 0) {
                    $check = $conn->prepare("
                        SELECT COUNT(*) FROM notificaciones 
                        WHERE usuario_id = ? 
                        AND tipo = 'vacaciones' 
                        AND DATE(fecha_creacion) = CURDATE()
                    ");
                    $check->execute([$usuario['id']]);
                    
                    if ($check->fetchColumn() == 0) {
                        self::vacacionesRestantes($usuario['id'], $dias_restantes);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error verificando vacaciones: " . $e->getMessage());
        }
    }
    
    /**
     * Marcar todas las notificaciones como leídas
     */
    public static function marcarTodasComoLeidas($usuario_id) {
        global $conn;
        
        try {
            $stmt = $conn->prepare("
                UPDATE notificaciones 
                SET leida = TRUE, fecha_lectura = NOW() 
                WHERE usuario_id = ? AND leida = FALSE
            ");
            return $stmt->execute([$usuario_id]);
        } catch (Exception $e) {
            error_log("Error marcando todas como leídas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Eliminar notificaciones antiguas (más de 30 días)
     */
    public static function eliminarAntiguas() {
        global $conn;
        
        try {
            $stmt = $conn->prepare("
                DELETE FROM notificaciones 
                WHERE fecha_creacion < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error eliminando notificaciones antiguas: " . $e->getMessage());
            return false;
        }
    }
}
?>