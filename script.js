// ============================================
// SCRIPT PRINCIPAL - FUNCIONES GLOBALES
// ============================================

/**
 * Alternar entre formularios de Login y Registro
 */
function showForm(formId) {
    document.querySelectorAll(".form-box").forEach(form => {
        form.classList.remove("active");
    });

    const targetForm = document.getElementById(formId);
    if (targetForm) {
        targetForm.classList.add("active");
        targetForm.style.animation = 'none';
        targetForm.offsetHeight;
        targetForm.style.animation = null;
    }
}

/**
 * Mostrar/Ocultar contraseñas
 */
function toggleVisibility(inputId, iconElement) {
    const passwordInput = document.getElementById(inputId);
    if (!passwordInput) return;

    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
    
    iconElement.classList.toggle('fa-eye', !isPassword);
    iconElement.classList.toggle('fa-eye-slash', isPassword);
}

// ============================================
// GESTIÓN DEL SIDEBAR
// ============================================

document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.querySelector(".sidebar");
    
    if (sidebar) {
        const sidebarToggler = document.querySelector(".sidebar-toggler");
        const menuToggler = document.querySelector(".menu-toggler");
        
        // Estado inicial (no colapsado)
        sidebar.classList.remove("collapsed");

        // Toggle colapsar sidebar
        if (sidebarToggler) {
            sidebarToggler.addEventListener("click", () => {
                sidebar.classList.toggle("collapsed");
                
                // Guardar preferencia en localStorage
                const isCollapsed = sidebar.classList.contains("collapsed");
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            });
        }

        // Toggle menú móvil
        if (menuToggler) {
            menuToggler.addEventListener("click", () => {
                sidebar.classList.toggle("mobile-open");
            });
        }

        // Restaurar estado guardado
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true') {
            sidebar.classList.add('collapsed');
        }

        // Cerrar sidebar al hacer clic fuera en móvil
        document.addEventListener('click', (event) => {
            const isMobile = window.innerWidth <= 768;
            const isClickInside = sidebar.contains(event.target);
            
            if (isMobile && !isClickInside && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
        });

        // Ajustar altura en resize
        window.addEventListener("resize", () => {
            if (window.innerWidth >= 1024) {
                sidebar.style.height = "calc(100vh - 32px)";
            } else {
                sidebar.classList.remove("collapsed");
                sidebar.style.height = "auto";
            }
        });
    }
});

// ============================================
// PREVISUALIZACIÓN DE IMAGEN DE PERFIL
// ============================================

function previewFile() {
    const preview = document.getElementById('preview-img');
    const fileInput = document.querySelector('input[type=file]');
    const submitBtn = document.getElementById('submit-photo');
    
    if (!preview || !fileInput || !submitBtn) return;
    
    const file = fileInput.files[0];
    if (!file) return;
    
    // Validar tipo de archivo
    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        mostrarNotificacion('Tipo de archivo no válido. Usa JPG, PNG, GIF o WEBP.', 'error');
        return;
    }
    
    // Validar tamaño (2MB)
    if (file.size > 2 * 1024 * 1024) {
        mostrarNotificacion('La imagen no puede superar los 2MB.', 'error');
        return;
    }
    
    const reader = new FileReader();

    reader.onloadend = function () {
        if (preview.tagName === 'IMG') {
            preview.src = reader.result;
        } else {
            // Si era un div con iniciales, lo reemplazamos por img
            const newImg = document.createElement('img');
            newImg.src = reader.result;
            newImg.className = 'profile-avatar-large';
            newImg.id = 'preview-img';
            newImg.alt = 'Foto de perfil';
            preview.parentNode.replaceChild(newImg, preview);
        }
        
        // Auto-submit después de la previsualización
        setTimeout(() => {
            submitBtn.click();
        }, 500);
    }
    
    reader.readAsDataURL(file);
}

// ============================================
// NOTIFICACIONES FLOTANTES
// ============================================

function mostrarNotificacion(mensaje, tipo = 'info') {
    // Eliminar notificaciones anteriores del mismo tipo
    const notificacionesAnteriores = document.querySelectorAll('.notificacion-flotante');
    notificacionesAnteriores.forEach(n => n.remove());
    
    // Crear nueva notificación
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion-flotante notificacion-${tipo}`;
    
    // Iconos según tipo
    const iconos = {
        success: 'fa-circle-check',
        error: 'fa-circle-exclamation',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };
    
    const icono = iconos[tipo] || iconos.info;
    
    notificacion.innerHTML = `
        <i class="fa-regular ${icono}"></i>
        <span>${mensaje}</span>
    `;
    
    // Estilos
    const colores = {
        success: '#22c55e',
        error: '#ef4444',
        warning: '#f97316',
        info: '#3b82f6'
    };
    
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colores[tipo] || colores.info};
        color: white;
        padding: 12px 24px;
        border-radius: 40px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 9999;
        font-size: 14px;
        font-weight: 500;
        animation: slideIn 0.3s ease;
        pointer-events: none;
    `;
    
    document.body.appendChild(notificacion);
    
    // Eliminar después de 3 segundos
    setTimeout(() => {
        notificacion.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notificacion.remove(), 300);
    }, 3000);
}

// ============================================
// CONTROL DE INACTIVIDAD (5 MINUTOS)
// ============================================

let tiempoInactivo;
const TIEMPO_INACTIVIDAD = 5 * 60 * 1000; // 5 minutos en milisegundos

const resetearTemporizador = () => {
    clearTimeout(tiempoInactivo);
    tiempoInactivo = setTimeout(() => {
        // Mostrar advertencia antes de cerrar
        const continuar = confirm('¿Sigues ahí? Por tu seguridad, cerrarás sesión por inactividad.');
        if (continuar) {
            resetearTemporizador();
        } else {
            window.location.href = 'logout.php?reason=timeout';
        }
    }, TIEMPO_INACTIVIDAD);
};

// Eventos que indican actividad del usuario
['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(evt => 
    document.addEventListener(evt, resetearTemporizador, { passive: true })
);

// Iniciar temporizador
resetearTemporizador();

// ============================================
// ANIMACIONES GLOBALES
// ============================================

// Añadir estilos de animación
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }
    
    .fade-in {
        animation: fadeIn 0.5s ease;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Scroll suave */
    html {
        scroll-behavior: smooth;
    }
    
    /* Mejoras para hover en elementos clickeables */
    .clickable {
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .clickable:hover {
        opacity: 0.8;
    }
    
    /* Tooltips personalizados */
    [data-tooltip] {
        position: relative;
        cursor: help;
    }
    
    [data-tooltip]:before {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #1e293b;
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s;
        pointer-events: none;
        z-index: 1000;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
    [data-tooltip]:hover:before {
        opacity: 1;
        visibility: visible;
        bottom: calc(100% + 8px);
    }
`;

document.head.appendChild(style);

// ============================================
// UTILIDADES
// ============================================

/**
 * Formatear fecha a formato local
 */
function formatearFecha(fecha, incluirHora = false) {
    const date = new Date(fecha);
    const opciones = {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    };
    
    if (incluirHora) {
        opciones.hour = '2-digit';
        opciones.minute = '2-digit';
    }
    
    return date.toLocaleDateString('es-ES', opciones);
}

/**
 * Formatear número de teléfono venezolano
 */
function formatearTelefono(telefono) {
    // Eliminar todo excepto números
    const numeros = telefono.replace(/\D/g, '');
    
    if (numeros.length === 11) {
        return `+58 ${numeros.slice(0,4)}-${numeros.slice(4)}`;
    } else if (numeros.length === 10) {
        return `+58 ${numeros.slice(0,4)}-${numeros.slice(4)}`;
    }
    
    return telefono;
}

/**
 * Formatear número como moneda
 */
function formatearMoneda(valor, moneda = 'USD') {
    return new Intl.NumberFormat('es-VE', {
        style: 'currency',
        currency: moneda,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(valor);
}

/**
 * Validar email
 */
function esEmailValido(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Validar teléfono venezolano
 */
function esTelefonoValido(telefono) {
    const re = /^\+?58?(4\d{2})-?\d{7}$/;
    return re.test(telefono);
}

// ============================================
// INICIALIZACIÓN
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    console.log('✅ script.js cargado correctamente');
    
    // Inicializar tooltips
    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.classList.add('clickable');
    });
    
    // Inicializar contadores si existen
    const contadores = document.querySelectorAll('[data-countdown]');
    contadores.forEach(contador => {
        const tiempo = parseInt(contador.dataset.countdown);
        if (!isNaN(tiempo)) {
            let segundos = tiempo;
            const intervalo = setInterval(() => {
                segundos--;
                contador.textContent = segundos;
                
                if (segundos <= 0) {
                    clearInterval(intervalo);
                    if (contador.dataset.countdownEnd) {
                        eval(contador.dataset.countdownEnd);
                    }
                }
            }, 1000);
        }
    });
});

// ============================================
// EXPORTAR FUNCIONES GLOBALES
// ============================================

window.mostrarNotificacion = mostrarNotificacion;
window.formatearFecha = formatearFecha;
window.formatearTelefono = formatearTelefono;
window.formatearMoneda = formatearMoneda;
window.esEmailValido = esEmailValido;
window.esTelefonoValido = esTelefonoValido;