/** Utilidades de interfaz de la app CERAMISHOP. */
(function () {
    "use strict";

    const PLACEHOLDER = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect width="400" height="300" fill="#f3f1ef"/>' +
        '<text x="200" y="160" font-family="Arial" font-size="18" fill="#8d7b72" text-anchor="middle">Imagen no disponible</text></svg>');

    function esc(s) {
        return String(s == null ? "" : s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    }

    function precio(v) {
        const n = Number(v) || 0;
        const dec = Math.abs(n - Math.round(n)) > 0.004 ? 2 : 0;
        return "$" + n.toLocaleString("es-CO", { minimumFractionDigits: dec, maximumFractionDigits: dec });
    }

    /** Estado de carga en un botón (evita doble toque). */
    function cargando(btn, activo, texto) {
        if (!btn) return;
        if (activo) {
            btn.dataset.original = btn.innerHTML;
            btn.disabled = true;
            btn.setAttribute("aria-busy", "true");
            btn.innerHTML = '<span class="mini-spinner" aria-hidden="true"></span> ' + esc(texto || "Procesando…");
        } else {
            btn.disabled = false;
            btn.removeAttribute("aria-busy");
            if (btn.dataset.original) btn.innerHTML = btn.dataset.original;
        }
    }

    /** Mensaje de estado (vacío / error) con botón opcional. */
    function estado(contenedor, { icono, titulo, texto, boton, accion, tipo }) {
        contenedor.innerHTML =
            '<div class="estado-vista ' + (tipo === "error" ? "estado-error" : "") + '" role="' + (tipo === "error" ? "alert" : "status") + '">' +
            '<div class="estado-icono" aria-hidden="true">' + (icono || "ℹ️") + "</div>" +
            "<h2>" + esc(titulo) + "</h2>" + (texto ? "<p>" + esc(texto) + "</p>" : "") +
            (boton ? '<button type="button" class="btn-principal estado-boton">' + esc(boton) + "</button>" : "") + "</div>";
        if (boton && accion) contenedor.querySelector(".estado-boton").addEventListener("click", accion);
    }

    /** Mensaje según el tipo de error de la API. */
    function mostrarError(contenedor, error, reintentar) {
        const iconos = { conexion: "📡", tiempo: "⏱️", servidor: "🛠️", no_encontrado: "🔍", stock: "📦" };
        const titulos = { conexion: "Sin conexión con el servidor", tiempo: "El servidor no respondió", servidor: "Error del servidor", no_encontrado: "No encontrado", stock: "Sin inventario suficiente" };
        estado(contenedor, {
            tipo: "error",
            icono: iconos[error.tipo] || "⚠️",
            titulo: titulos[error.tipo] || "Algo salió mal",
            texto: error.message || "Intenta de nuevo.",
            boton: reintentar ? "Reintentar" : null,
            accion: reintentar
        });
    }

    function esqueletos(contenedor, n) {
        contenedor.innerHTML = Array.from({ length: n || 4 }, () =>
            '<div class="producto-card esqueleto-card" aria-hidden="true"><div class="esqueleto esq-img"></div><div class="esqueleto esq-linea"></div><div class="esqueleto esq-linea corta"></div></div>'
        ).join("") + '<span class="sr-only" role="status">Cargando…</span>';
    }

    /** Imagen con carga diferida y respaldo si no se puede cargar. */
    function img(src, alt, clase) {
        return '<img src="' + esc(src || PLACEHOLDER) + '" alt="' + esc(alt) + '" class="' + esc(clase || "") + '" loading="lazy" decoding="async" data-respaldo="1">';
    }

    // Si una imagen http falla dentro de la app (contenido mixto), se intenta descargarla de forma nativa
    document.addEventListener("error", async function (ev) {
        const el = ev.target;
        if (!(el instanceof HTMLImageElement) || !el.dataset.respaldo || el.dataset.intentado) return;
        el.dataset.intentado = "1";
        const original = el.getAttribute("src");
        const plugin = window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.CapacitorHttp;
        if (plugin && /^https?:/.test(original)) {
            try {
                const r = await plugin.get({ url: original, responseType: "blob" });
                if (r.status === 200 && r.data) {
                    const tipo = /\.png$/i.test(original) ? "image/png" : /\.webp$/i.test(original) ? "image/webp" : "image/jpeg";
                    el.src = "data:" + tipo + ";base64," + r.data;
                    return;
                }
            } catch (e) { /* se usa la imagen de respaldo */ }
        }
        el.src = PLACEHOLDER;
    }, true);

    function leer(clave, porDefecto) {
        try { const v = JSON.parse(localStorage.getItem(clave)); return v == null ? porDefecto : v; } catch (e) { return porDefecto; }
    }
    function guardar(clave, valor) { localStorage.setItem(clave, JSON.stringify(valor)); }

    /** Agrega al carrito respetando el inventario. Devuelve {ok, mensaje}. */
    function agregarAlCarrito(producto, cantidad) {
        cantidad = parseInt(cantidad, 10);
        if (!cantidad || cantidad < 1) return { ok: false, mensaje: "Escribe una cantidad válida." };
        if (producto.stock <= 0) return { ok: false, mensaje: "Este producto está agotado." };
        const carrito = leer("carrito", []);
        const existente = carrito.find(p => p.id === producto.id);
        const actual = existente ? Number(existente.cantidad) : 0;
        if (actual + cantidad > producto.stock) {
            return { ok: false, mensaje: "Solo hay " + producto.stock + " unidades disponibles" + (actual ? " (ya tienes " + actual + " en el carrito)." : ".") };
        }
        if (existente) {
            existente.cantidad = actual + cantidad;
            existente.precio = producto.precio;
            existente.stock = producto.stock;
        } else {
            carrito.push({ id: producto.id, nombre: producto.nombre, precio: producto.precio, stock: producto.stock, imagen: producto.imagen, categoria: producto.categoria, descripcion: producto.descripcion, cantidad });
        }
        guardar("carrito", carrito);
        actualizarBadge();
        return { ok: true, mensaje: producto.nombre + " agregado (x" + cantidad + ")" };
    }

    function actualizarBadge() {
        const n = leer("carrito", []).reduce((s, p) => s + (Number(p.cantidad) || 0), 0);
        document.querySelectorAll("[data-badge-carrito]").forEach(b => { b.textContent = n; b.hidden = !n; });
    }

    // Marca la pestaña activa de la barra inferior y el contador del carrito
    document.addEventListener("DOMContentLoaded", function () {
        const actual = location.pathname.split("/").pop() || "home.html";
        document.querySelectorAll(".bottom-nav a").forEach(a => {
            if ((a.getAttribute("href") || "").split("/").pop().split("#")[0] === actual) a.setAttribute("aria-current", "page");
        });
        actualizarBadge();
    });

    window.addEventListener("offline", () => window.mostrarToast && mostrarToast("Sin conexión a internet"));
    window.addEventListener("online", () => window.mostrarToast && mostrarToast("Conexión restablecida"));

    window.UI = { esc, precio, cargando, estado, mostrarError, esqueletos, img, leer, guardar, agregarAlCarrito, actualizarBadge, PLACEHOLDER };
})();
