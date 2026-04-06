# Proteger archivos de log
<Files "*.log">
    Order Allow,Deny
    Deny from all
</Files>

# Proteger archivos de configuración
<Files "config.php">
    Order Allow,Deny
    Deny from all
</Files>

# Proteger archivos de helpers
<Files "notificaciones_helper.php">
    Order Allow,Deny
    Deny from all
</Files>

# Permitir acceso a cron solo con parámetro
<Files "cron_notificaciones.php">
    Order Allow,Deny
    Deny from all
    <FilesMatch "cron_notificaciones\.php">
        Order Deny,Allow
        Allow from env=CRON_ALLOWED
    </FilesMatch>
</Files>