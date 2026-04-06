// ============================================
// CONTROL DE SESIÓN POR INACTIVIDAD - VERSIÓN CORREGIDA
// ============================================

class SessionTimeout {
    constructor(options = {}) {
        this.timeout = options.timeout || 60; // 60 segundos totales
        this.warningTime = options.warningTime || 10; // Advertencia cuando quedan 10 segundos
        this.timer = null;
        this.warningTimer = null;
        this.remainingTime = this.timeout;
        this.isActive = true;
        this.modalVisible = false;
        
        this.createModal();
        this.resetTimer();
        this.initActivityListeners();
    }
    
    createModal() {
        // Estilos
        const style = document.createElement('style');
        style.textContent = `
            .session-modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(5px);
                z-index: 9999;
                align-items: center;
                justify-content: center;
            }
            
            .session-modal.show {
                display: flex;
            }
            
            .session-card {
                background: white;
                border-radius: 24px;
                padding: 40px;
                max-width: 400px;
                width: 90%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: slideUp 0.3s ease;
            }
            
            .timer-container {
                position: relative;
                width: 120px;
                height: 120px;
                margin: 0 auto 30px;
            }
            
            .timer-circle-bg {
                fill: none;
                stroke: #e2e8f0;
                stroke-width: 4;
            }
            
            .timer-circle-progress {
                fill: none;
                stroke: #3b82f6;
                stroke-width: 4;
                stroke-linecap: round;
                transform: rotate(-90deg);
                transform-origin: 50% 50%;
                transition: stroke-dashoffset 1s linear;
            }
            
            .timer-text {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 32px;
                font-weight: 700;
                color: #0f172a;
            }
            
            .timer-text small {
                font-size: 14px;
                color: #64748b;
                display: block;
            }
            
            .session-buttons {
                display: flex;
                gap: 15px;
                justify-content: center;
            }
            
            .btn-continue {
                background: #3b82f6;
                color: white;
                border: none;
                padding: 14px 30px;
                border-radius: 40px;
                font-weight: 600;
                font-size: 15px;
                cursor: pointer;
                flex: 1;
            }
            
            .btn-continue:hover {
                background: #2563eb;
            }
            
            .btn-logout {
                background: #f1f5f9;
                color: #64748b;
                border: none;
                padding: 14px 30px;
                border-radius: 40px;
                font-weight: 600;
                font-size: 15px;
                cursor: pointer;
                flex: 1;
            }
            
            .btn-logout:hover {
                background: #fee2e2;
                color: #ef4444;
            }
            
            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            body.dark-mode .session-card {
                background: #1e293b;
            }
            
            body.dark-mode .session-card h3 {
                color: #f1f5f9;
            }
            
            body.dark-mode .timer-circle-bg {
                stroke: #334155;
            }
            
            body.dark-mode .timer-text {
                color: #f1f5f9;
            }
        `;
        document.head.appendChild(style);
        
        // HTML del modal
        const modal = document.createElement('div');
        modal.className = 'session-modal';
        modal.id = 'sessionTimeoutModal';
        modal.innerHTML = `
            <div class="session-card">
                <h3>¿Sigues ahí?</h3>
                <p>Tu sesión se cerrará por inactividad</p>
                
                <div class="timer-container">
                    <svg width="120" height="120" viewBox="0 0 120 120">
                        <circle class="timer-circle-bg" cx="60" cy="60" r="52" />
                        <circle class="timer-circle-progress" cx="60" cy="60" r="52" 
                                stroke-dasharray="326" stroke-dashoffset="0" />
                    </svg>
                    <div class="timer-text" id="timerDisplay">
                        10
                        <small>seg</small>
                    </div>
                </div>
                
                <div class="session-buttons">
                    <button class="btn-continue" id="continueSessionBtn">
                        Continuar
                    </button>
                    <button class="btn-logout" id="logoutNowBtn">
                        Cerrar ahora
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        this.modal = document.getElementById('sessionTimeoutModal');
        this.timerDisplay = document.getElementById('timerDisplay');
        this.progressCircle = document.querySelector('.timer-circle-progress');
        this.continueBtn = document.getElementById('continueSessionBtn');
        this.logoutBtn = document.getElementById('logoutNowBtn');
        
        this.continueBtn.addEventListener('click', () => this.continueSession());
        this.logoutBtn.addEventListener('click', () => this.logout());
    }
    
    updateTimerDisplay(seconds) {
        const totalSeconds = this.warningTime;
        const elapsed = totalSeconds - seconds;
        const percentage = (elapsed / totalSeconds) * 100;
        const dashOffset = 326 - (326 * percentage / 100);
        
        this.timerDisplay.innerHTML = `${seconds}<small>seg</small>`;
        this.progressCircle.style.strokeDashoffset = dashOffset;
    }
    
    showWarning() {
        if (this.modalVisible) return;
        
        this.isActive = false;
        this.modalVisible = true;
        this.modal.classList.add('show');
        
        let secondsLeft = this.warningTime;
        this.updateTimerDisplay(secondsLeft);
        
        this.warningTimer = setInterval(() => {
            secondsLeft--;
            this.updateTimerDisplay(secondsLeft);
            
            if (secondsLeft <= 0) {
                clearInterval(this.warningTimer);
                this.logout();
            }
        }, 1000);
    }
    
    continueSession() {
        this.modal.classList.remove('show');
        this.modalVisible = false;
        clearInterval(this.warningTimer);
        this.isActive = true;
        this.resetTimer();
    }
    
    logout() {
        clearInterval(this.timer);
        clearInterval(this.warningTimer);
        window.location.href = 'logout.php?reason=timeout';
    }
    
    resetTimer() {
        clearInterval(this.timer);
        this.remainingTime = this.timeout;
        
        this.timer = setInterval(() => {
            this.remainingTime--;
            
            if (this.remainingTime === this.warningTime && this.isActive && !this.modalVisible) {
                this.showWarning();
                clearInterval(this.timer);
            }
            
            if (this.remainingTime <= 0 && this.isActive) {
                this.logout();
            }
        }, 1000);
    }
    
    initActivityListeners() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        const resetActivity = () => {
            if (this.isActive && !this.modalVisible) {
                this.resetTimer();
            }
        };
        
        events.forEach(event => {
            document.addEventListener(event, resetActivity);
        });
    }
}

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    const authPages = [
        'admin_page.php', 'user_page.php', 'profile.php',
        'calendar.php', 'activity.php', 'departments.php',
        'positions.php', 'users_list.php', 'ausencias.php'
    ];
    
    const currentPage = window.location.pathname.split('/').pop();
    
    if (authPages.includes(currentPage)) {
        new SessionTimeout({ timeout: 180, warningTime: 60 }); // 60 segundos para pruebas
    }
});