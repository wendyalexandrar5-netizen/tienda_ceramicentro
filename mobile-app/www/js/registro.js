/** Registro de clientes desde la app (inicia sesión automáticamente). */
(function () {
    "use strict";
    const form = document.getElementById("registroForm");
    const btn = document.getElementById("btnRegistro");
    const msg = document.getElementById("mensaje");

    form.addEventListener("submit", async ev => {
        ev.preventDefault();
        if (btn.disabled) return;
        msg.hidden = true;
        const nombre = document.getElementById("nombre").value.trim();
        const correo = document.getElementById("correo").value.trim().toLowerCase();
        const password = document.getElementById("password").value;
        const confirmar = document.getElementById("confirmar").value;

        if (nombre.length < 3) return mostrar("El nombre debe tener al menos 3 caracteres.", "nombre");
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) return mostrar("Escribe un correo válido.", "correo");
        if (password.length < 8 || !/[A-Za-zÁÉÍÓÚáéíóúÑñ]/.test(password) || !/\d/.test(password)) return mostrar("La contraseña debe tener mínimo 8 caracteres, con letras y números.", "password");
        if (password !== confirmar) return mostrar("Las contraseñas no coinciden.", "confirmar");
        if (!document.getElementById("acepto").checked) return mostrar("Debes aceptar los términos para crear tu cuenta.", "acepto");

        UI.cargando(btn, true, "Registrando…");
        try {
            const data = await Api.post("api_registro.php", { nombre, correo, password });
            Api.guardarSesion(data.usuario, data.token);
            mostrarToast("¡Cuenta creada! Bienvenido(a)", "ok");
            setTimeout(() => location.replace("../home.html"), 900);
        } catch (error) {
            mostrar(error.message, error.datos && error.datos.campo);
            UI.cargando(btn, false);
        }
    });

    function mostrar(t, campo) {
        msg.textContent = t;
        msg.hidden = false;
        const el = campo && document.getElementById(campo);
        if (el) el.focus();
    }
})();
