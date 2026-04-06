// ============================================
// AUTENTICACIÓN - FUNCIONES COMPARTIDAS
// ============================================

/**
 * Cambiar entre formularios de login y registro
 */
function showForm(formType) {
    const forms = ['login', 'register'];
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const loginTab = document.querySelector('.auth-tab[data-form="login"]');
    const registerTab = document.querySelector('.auth-tab[data-form="register"]');
    
    if (!loginForm || !registerForm || !loginTab || !registerTab) return;
    
    if (formType === 'login') {
        loginForm.classList.add('active');
        registerForm.classList.remove('active');
        loginTab.classList.add('active');
        registerTab.classList.remove('active');
    } else {
        registerForm.classList.add('active');
        loginForm.classList.remove('active');
        registerTab.classList.add('active');
        loginTab.classList.remove('active');
    }
    
    // Actualizar URL sin recargar
    const url = new URL(window.location);
    url.searchParams.set('form', formType);
    window.history.pushState({}, '', url);
}

/**
 * Mostrar/ocultar contraseña
 */
function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    
    // Cambiar icono
    if (icon.classList.contains('fa-eye')) {
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

/**
 * Inicializar selector de fechas
 */
function initDateSelector() {
    const daySelect = document.getElementById('day');
    const monthSelect = document.getElementById('month');
    const yearSelect = document.getElementById('year');

    if (!daySelect || !monthSelect || !yearSelect) {
        console.log('Selector de fecha no encontrado');
        return;
    }

    // Limpiar opciones existentes
    daySelect.innerHTML = '<option value="">Día</option>';
    monthSelect.innerHTML = '<option value="">Mes</option>';
    yearSelect.innerHTML = '<option value="">Año</option>';

    // Años (desde 1900 hasta año actual - 18)
    const currentYear = new Date().getFullYear();
    for (let year = currentYear - 18; year >= 1900; year--) {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        yearSelect.appendChild(option);
    }

    // Meses
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    months.forEach((month, index) => {
        const option = document.createElement('option');
        option.value = index + 1;
        option.textContent = month;
        monthSelect.appendChild(option);
    });

    // Función para actualizar días según mes/año
    function updateDays() {
        const month = parseInt(monthSelect.value);
        const year = parseInt(yearSelect.value);
        
        if (!month || !year) {
            // Si falta mes o año, mostrar días por defecto
            daySelect.innerHTML = '<option value="">Día</option>';
            for (let d = 1; d <= 31; d++) {
                const option = document.createElement('option');
                option.value = d;
                option.textContent = d;
                daySelect.appendChild(option);
            }
            return;
        }

        // Calcular días correctos para el mes
        const daysInMonth = new Date(year, month, 0).getDate();
        daySelect.innerHTML = '<option value="">Día</option>';
        
        for (let day = 1; day <= daysInMonth; day++) {
            const option = document.createElement('option');
            option.value = day;
            option.textContent = day;
            daySelect.appendChild(option);
        }
    }

    monthSelect.addEventListener('change', updateDays);
    yearSelect.addEventListener('change', updateDays);
    
    // Inicializar con días por defecto
    updateDays();
}

/**
 * Validar formulario de registro
 */
function validateRegisterForm(e) {
    const password = document.getElementById('register-password').value;
    const confirm = document.getElementById('confirm-password').value;
    const terms = document.getElementById('terms') || { checked: true };
    
    // Validar contraseñas
    if (password !== confirm) {
        e.preventDefault();
        mostrarNotificacion('Las contraseñas no coinciden', 'error');
        return false;
    }
    
    // Validar términos (si existe el checkbox)
    if (!terms.checked) {
        e.preventDefault();
        mostrarNotificacion('Debes aceptar los términos y condiciones', 'warning');
        return false;
    }
    
    // Validar fortaleza de contraseña
    const passwordStrength = checkPasswordStrength(password);
    if (passwordStrength < 3) {
        const confirmar = confirm('La contraseña es débil. ¿Deseas continuar de todos modos?');
        if (!confirmar) {
            e.preventDefault();
            return false;
        }
    }
    
    return true;
}

/**
 * Verificar fortaleza de contraseña
 */
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    return strength;
}

/**
 * Mostrar notificaciones flotantes
 */
function mostrarNotificacion(mensaje, tipo = 'info') {
    // Crear elemento de notificación
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion notificacion-${tipo}`;
    
    // Icono según tipo
    let icono = 'fa-circle-info';
    if (tipo === 'success') icono = 'fa-circle-check';
    if (tipo === 'error') icono = 'fa-circle-exclamation';
    if (tipo === 'warning') icono = 'fa-triangle-exclamation';
    
    notificacion.innerHTML = `
        <i class="fa-regular ${icono}"></i>
        <span>${mensaje}</span>
    `;
    
    // Estilos
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${tipo === 'success' ? '#22c55e' : tipo === 'error' ? '#ef4444' : tipo === 'warning' ? '#f97316' : '#3b82f6'};
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
    `;
    
    document.body.appendChild(notificacion);
    
    // Eliminar después de 3 segundos
    setTimeout(() => {
        notificacion.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notificacion.remove(), 300);
    }, 3000);
}

/**
 * Inicialización al cargar la página
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ auth.js cargado correctamente');
    
    // Inicializar selector de fechas
    initDateSelector();

    // Event listeners para pestañas (si existen)
    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const form = this.dataset.form;
            showForm(form);
        });
    });

    // Validar formulario de registro
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', validateRegisterForm);
    }

    // Mantener formulario activo según URL
    const params = new URLSearchParams(window.location.search);
    const form = params.get('form');
    if (form === 'register') {
        showForm('register');
    } else if (form === 'login') {
        showForm('login');
    }
    
    // Añadir animaciones globales
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
        
        .notificacion {
            pointer-events: none;
        }
    `;
    document.head.appendChild(style);
});