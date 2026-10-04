/**
 * Cliente de la API de CERAMISHOP para la app.
 * - Usa CapacitorHttp (nativo) cuando existe; si no, fetch (navegador).
 * - Tiempo de espera, detección de "sin conexión" y mensajes claros por tipo de error.
 * - Evita peticiones duplicadas mientras una igual está en curso.
 * - Envía el token de sesión (X-Auth-Token) y cierra la sesión si expiró.
 */
(function () {
    "use strict";

    const enCurso = new Map();

    class ErrorApi extends Error {
        constructor(tipo, mensaje, datos) {
            super(mensaje);
            this.tipo = tipo;          // conexion | tiempo | servidor | validacion | sesion | stock | no_encontrado
            this.datos = datos || {};
        }
    }

    function sesion() {
        try { return JSON.parse(localStorage.getItem("usuario")); } catch (e) { return null; }
    }
    function token() { return localStorage.getItem("token") || ""; }

    function cerrarSesionLocal() {
        localStorage.removeItem("usuario");
        localStorage.removeItem("token");
    }

    function rutaRaiz() {
        // Las pantallas internas están en /pages/
        return location.pathname.indexOf("/pages/") !== -1 ? "../" : "";
    }

    function irALogin(mensaje) {
        if (mensaje) sessionStorage.setItem("aviso_login", mensaje);
        location.href = rutaRaiz() + "pages/login.html";
    }

    function clasificar(estado, datos) {
        const codigo = (datos && datos.codigo) || "";
        const msg = (datos && datos.message) || "";
        if (estado === 401 || codigo === "sesion_expirada" || codigo === "sesion_requerida") {
            return new ErrorApi("sesion", msg || "Tu sesión expiró. Inicia sesión de nuevo.", datos);
        }
        if (estado === 409 || codigo === "sin_stock") return new ErrorApi("stock", msg || "No hay inventario suficiente.", datos);
        if (estado === 404) return new ErrorApi("no_encontrado", msg || "No encontramos lo que buscas.", datos);
        if (estado === 422 || estado === 429 || estado === 403) return new ErrorApi("validacion", msg || "Revisa los datos e intenta de nuevo.", datos);
        if (estado >= 500) return new ErrorApi("servidor", msg || "El servidor tuvo un problema. Intenta de nuevo en unos minutos.", datos);
        return new ErrorApi("servidor", msg || "Respuesta inesperada del servidor.", datos);
    }

    async function ejecutar(metodo, ruta, datos) {
        if (navigator.onLine === false) {
            throw new ErrorApi("conexion", "No tienes conexión a internet. Revisa tu red e intenta de nuevo.");
        }
        const url = window.CS_APP.SERVIDOR + "/api/" + ruta;
        const headers = { "Accept": "application/json" };
        if (datos) headers["Content-Type"] = "application/json";
        if (token()) headers["X-Auth-Token"] = token();
        const limite = window.CS_APP.TIEMPO_ESPERA_MS;

        let estado, cuerpo;
        try {
            const plugin = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.CapacitorHttp;
            if (plugin) {
                const opciones = { url, headers, connectTimeout: limite, readTimeout: limite };
                if (datos) opciones.data = datos;
                const r = metodo === "GET" ? await plugin.get(opciones) : await plugin.post(opciones);
                estado = r.status;
                cuerpo = r.data;
            } else {
                const ctrl = new AbortController();
                const t = setTimeout(() => ctrl.abort(), limite);
                try {
                    const r = await fetch(url, { method: metodo, headers, body: datos ? JSON.stringify(datos) : undefined, signal: ctrl.signal });
                    estado = r.status;
                    cuerpo = await r.text();
                } finally {
                    clearTimeout(t);
                }
            }
        } catch (e) {
            const texto = String(e && (e.message || e));
            if (e && e.name === "AbortError" || /timeout|timed out/i.test(texto)) {
                throw new ErrorApi("tiempo", "El servidor tardó demasiado en responder. Revisa tu conexión e intenta de nuevo.");
            }
            throw new ErrorApi("conexion", "No pudimos conectarnos con el servidor de CERAMISHOP. Verifica tu conexión a internet o a la red de la tienda.");
        }

        if (typeof cuerpo === "string") {
            try { cuerpo = cuerpo ? JSON.parse(cuerpo) : {}; } catch (e) {
                console.error("Respuesta no JSON de", ruta, estado);
                throw new ErrorApi("servidor", "El servidor respondió con un formato inesperado.");
            }
        }
        const fallo = estado >= 400 || (cuerpo && typeof cuerpo === "object" && !Array.isArray(cuerpo) && cuerpo.success === false);
        if (fallo) {
            const err = clasificar(estado, cuerpo);
            if (err.tipo === "sesion" && ruta !== "api_login.php") {
                cerrarSesionLocal();
                irALogin(err.message);
            }
            throw err;
        }
        return cuerpo;
    }

    /** Petición a la API. Las peticiones idénticas en curso se reutilizan (no se duplican). */
    function pedir(metodo, ruta, datos) {
        const clave = metodo + " " + ruta + " " + (datos ? JSON.stringify(datos) : "");
        if (enCurso.has(clave)) return enCurso.get(clave);
        const p = ejecutar(metodo, ruta, datos).finally(() => enCurso.delete(clave));
        enCurso.set(clave, p);
        return p;
    }

    window.Api = {
        ErrorApi,
        get: (ruta) => pedir("GET", ruta),
        post: (ruta, datos) => pedir("POST", ruta, datos || {}),
        sesion,
        token,
        cerrarSesionLocal,
        irALogin,
        guardarSesion(usuario, tk) {
            localStorage.setItem("usuario", JSON.stringify(usuario));
            if (tk) localStorage.setItem("token", tk);
        },
        /** Redirige al login si no hay sesión (pantallas privadas). */
        requerirSesion() {
            const u = sesion();
            if (!u || !token()) {
                irALogin("Inicia sesión para continuar.");
                return null;
            }
            return u;
        }
    };
})();
