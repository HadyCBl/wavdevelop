class SessionManager {
  constructor(config = {}) {
    this.config = {
      checkInterval: 60000, // Verificar cada minuto
      warningTime: 300, // Advertir 5 minutos antes
      sessionDuration: 3600, // 1 hora por defecto
      keepAliveUrl: BASE_URL_FOR_JS + "/src/cruds/keep_alive.php",
      loginUrl: BASE_URL_FOR_JS + "/index.php",
      showLogs: false,
      ...config,
    };

    this.isWarningShown = false;
    this.warningTimer = null;
    this.checkTimer = null;
    this.lastActivity = Date.now();

    this.init();
  }

  init() {
    this.log("SessionManager inicializado");
    this.startMonitoring();
    this.attachActivityListeners();
    console.log("Configuración del SessionManager:", this.config);
  }

  startMonitoring() {
    this.checkTimer = setInterval(() => {
      this.checkSession();
    }, this.config.checkInterval);

    this.log("Monitoreo de sesión iniciado");
  }

  async checkSession() {
    try {
      const response = await fetch(this.config.keepAliveUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ action: "check_session" }),
      });

      const data = await response.json();

      if (data.status === "expired") {
        this.handleSessionExpired();
        return;
      }

      if (data.status === "active") {
        const timeRemaining = data.time_remaining;
        this.log(`Tiempo restante: ${timeRemaining} segundos`);

        // Mostrar advertencia si queda poco tiempo
        if (timeRemaining <= this.config.warningTime && !this.isWarningShown) {
          this.showExpirationWarning(timeRemaining);
        }

        // Auto-extender si hay actividad reciente
        const timeSinceActivity = (Date.now() - this.lastActivity) / 1000;
        if (timeSinceActivity < 300 && timeRemaining < 600) {
          // Si hay actividad en últimos 5 min y quedan menos de 10 min
          this.extendSession();
        }
      }
    } catch (error) {
      this.log("Error verificando sesión:", error);
    }
  }

  showExpirationWarning(timeRemaining) {
    this.isWarningShown = true;
    const minutes = Math.floor(timeRemaining / 60);
    const seconds = timeRemaining % 60;

    Swal.fire({
      title: "⏰ Sesión por Expirar",
      html: `
                <div class="text-center">
                    <i class="fa fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <p class="mb-3">Tu sesión expirará en:</p>
                    <div class="alert alert-warning">
                        <h4 id="countdown-timer" class="mb-0">
                            ${minutes}:${seconds.toString().padStart(2, "0")}
                        </h4>
                    </div>
                    <p class="text-muted">¿Deseas extender tu sesión?</p>
                </div>
            `,
      icon: null,
      showCancelButton: true,
      confirmButtonText: '<i class="fa fa-clock"></i> Extender Sesión',
      cancelButtonText: '<i class="fa fa-sign-out-alt"></i> Cerrar Sesión',
      confirmButtonColor: "#28a745",
      cancelButtonColor: "#dc3545",
      allowOutsideClick: false,
      allowEscapeKey: false,
      timer: timeRemaining * 1000,
      timerProgressBar: true,
      didOpen: () => {
        this.startCountdown(timeRemaining);
      },
    }).then((result) => {
      if (result.isConfirmed) {
        this.extendSession();
      } else if (result.isDismissed) {
        if (result.dismiss === Swal.DismissReason.cancel) {
          this.logout();
        } else if (result.dismiss === Swal.DismissReason.timer) {
          this.handleSessionExpired();
        }
      }
    });
  }

  startCountdown(initialSeconds) {
    let seconds = initialSeconds;

    this.warningTimer = setInterval(() => {
      seconds--;
      const minutes = Math.floor(seconds / 60);
      const secs = seconds % 60;

      const timerElement = document.getElementById("countdown-timer");
      if (timerElement) {
        timerElement.textContent = `${minutes}:${secs.toString().padStart(2, "0")}`;
      }

      if (seconds <= 0) {
        clearInterval(this.warningTimer);
      }
    }, 1000);
  }

  async extendSession() {
    try {
      const response = await fetch(this.config.keepAliveUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ action: "extend_session" }),
      });

      const data = await response.json();

      if (data.status === "extended") {
        this.isWarningShown = false;
        this.lastActivity = Date.now();

        // Cerrar modal de advertencia si está abierto
        Swal.close();

        // Mostrar confirmación
        iziToast.success({
          title: "Sesión Extendida",
          message: "Tu sesión ha sido extendida exitosamente",
          position: "topRight",
          timeout: 3000,
        });

        this.log("Sesión extendida exitosamente");
      } else {
        this.handleSessionExpired();
      }
    } catch (error) {
      this.log("Error extendiendo sesión:", error);
      this.handleSessionExpired();
    }
  }

  handleSessionExpired() {
    clearInterval(this.checkTimer);
    clearInterval(this.warningTimer);

    Swal.fire({
      title: "🔒 Sesión Expirada",
      text: "Tu sesión ha expirado por seguridad. Serás redirigido al login.",
      icon: "error",
      confirmButtonText: "Ir al Login",
      allowOutsideClick: false,
      allowEscapeKey: false,
    }).then(() => {
      window.location.href = this.config.loginUrl;
    });
  }

  logout() {
    window.location.href = this.config.loginUrl;
  }

  attachActivityListeners() {
    // Detectar actividad del usuario
    const events = [
      "mousedown",
      "mousemove",
      "keypress",
      "scroll",
      "touchstart",
      "click",
    ];

    events.forEach((event) => {
      document.addEventListener(
        event,
        () => {
          this.lastActivity = Date.now();
        },
        { passive: true }
      );
    });
  }

  log(message, ...args) {
    if (this.config.showLogs) {
      console.log(`[SessionManager] ${message}`, ...args);
    }
  }

  destroy() {
    clearInterval(this.checkTimer);
    clearInterval(this.warningTimer);
    this.log("SessionManager destruido");
  }
}

// Función global para inicializar
window.initSessionManager = function (config = {}) {
  if (window.sessionManager) {
    window.sessionManager.destroy();
  }

  window.sessionManager = new SessionManager(config);
  return window.sessionManager;
};
