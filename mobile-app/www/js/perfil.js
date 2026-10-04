/** Perfil del usuario y cierre de sesión. */
(function () {
    "use strict";
    const usuario = Api.requerirSesion();
    if (!usuario) return;
    document.getElementById("nombreUsuario").textContent = usuario.nombre || "Usuario";
    document.getElementById("correoUsuario").textContent = usuario.correo || "";
    document.getElementById("rolUsuario").textContent = usuario.rol === "cliente" ? "Cliente" : (usuario.rol || "Cliente");
    const wa = document.getElementById("btnWhatsapp");
    if (window.CS_APP.WHATSAPP) wa.href = "https://wa.me/" + window.CS_APP.WHATSAPP + "?text=" + encodeURIComponent("Hola CERAMICENTRO, escribo desde la app.");
    else wa.hidden = true;

    const btn = document.getElementById("btnCerrarSesion");
    btn.addEventListener("click", async () => {
        if (!confirm("¿Cerrar sesión?")) return;
        UI.cargando(btn, true, "Cerrando…");
        try { await Api.post("api_logout.php", {}); } catch (e) { /* aunque falle la red, se cierra localmente */ }
        Api.cerrarSesionLocal();
        location.replace("login.html");
    });
})();
