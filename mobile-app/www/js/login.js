/** Inicio de sesión de clientes en la app. */
(function () {
    "use strict";
    const form = document.getElementById("loginForm");
    const btn = document.getElementById("btnLogin");
    const msg = document.getElementById("mensaje");

    const aviso = sessionStorage.getItem("aviso_login");
    if (aviso) {
        sessionStorage.removeItem("aviso_login");
        msg.textContent = aviso;
        msg.hidden = false;
    }
    if (Api.sesion() && Api.token()) location.replace("../home.html");

    form.addEventListener("submit", async ev => {
        ev.preventDefault();
        if (btn.disabled) return;
        msg.hidden = true;
        const correo = document.getElementById("correo").value.trim().toLowerCase();
        const password = document.getElementById("password").value;
        if (!correo || !password) return mostrar("Escribe tu correo y tu contraseña.");
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) return mostrar("Escribe un correo válido, por ejemplo nombre@correo.com.");

        UI.cargando(btn, true, "Ingresando…");
        try {
            const data = await Api.post("api_login.php", { correo, password });
            Api.guardarSesion(data.usuario, data.token);
            const volver = sessionStorage.getItem("volver_a");
            sessionStorage.removeItem("volver_a");
            location.replace(volver || "../home.html");
        } catch (error) {
            mostrar(error.message);
        } finally {
            UI.cargando(btn, false);
        }
    });

    // La recuperación de contraseña se hace en la web (se abre en el navegador del celular)
    document.getElementById("olvideClave").addEventListener("click", ev => {
        ev.preventDefault();
        window.open(window.CS_APP.SERVIDOR + "/recuperar_clave.php", "_blank");
    });

    function mostrar(t) {
        msg.textContent = t;
        msg.hidden = false;
    }
})();
