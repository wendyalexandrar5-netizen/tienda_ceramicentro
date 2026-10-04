/** Carrito de compras de la app. */
(function () {
    "use strict";
    const lista = document.getElementById("listaCarrito");
    const resumen = document.getElementById("resumenCarrito");
    const totalTexto = document.getElementById("totalCarrito");

    function cargar() {
        const carrito = UI.leer("carrito", []);
        UI.actualizarBadge();
        if (!carrito.length) {
            resumen.hidden = true;
            UI.estado(lista, { icono: "🛒", titulo: "Tu carrito está vacío", texto: "Agrega productos desde el catálogo.", boton: "Ir al catálogo", accion: () => location.href = "../home.html" });
            return;
        }
        let total = 0;
        lista.innerHTML = carrito.map((p, i) => {
            const cant = Number(p.cantidad) || 1;
            const sub = Number(p.precio) * cant;
            total += sub;
            return '<article class="carrito-item">' + UI.img(p.imagen || p.imagenFinal, p.nombre, "carrito-img") +
                '<div class="carrito-info"><h3>' + UI.esc(p.nombre) + "</h3>" +
                '<p class="nota">' + UI.precio(p.precio) + " c/u</p>" +
                '<div class="cantidad-box izquierda"><button type="button" class="btn-cant" data-i="' + i + '" data-cambio="-1" aria-label="Disminuir cantidad de ' + UI.esc(p.nombre) + '">−</button>' +
                '<span class="cantidad-valor" aria-live="polite">' + cant + "</span>" +
                '<button type="button" class="btn-cant" data-i="' + i + '" data-cambio="1" aria-label="Aumentar cantidad de ' + UI.esc(p.nombre) + '">+</button></div>' +
                '<p class="subtotal">Subtotal: <b>' + UI.precio(sub) + "</b></p></div>" +
                '<button type="button" class="btn-eliminar" data-eliminar="' + i + '" aria-label="Eliminar ' + UI.esc(p.nombre) + ' del carrito">🗑️</button></article>';
        }).join("");
        totalTexto.textContent = UI.precio(total);
        resumen.hidden = false;
    }

    lista.addEventListener("click", ev => {
        const carrito = UI.leer("carrito", []);
        const c = ev.target.closest("[data-cambio]");
        if (c) {
            const p = carrito[+c.dataset.i];
            const nueva = (Number(p.cantidad) || 1) + Number(c.dataset.cambio);
            if (nueva < 1) return;
            if (p.stock && nueva > p.stock) return mostrarToast("Solo hay " + p.stock + " unidades disponibles", "error");
            p.cantidad = nueva;
            UI.guardar("carrito", carrito);
            cargar();
        }
        const e = ev.target.closest("[data-eliminar]");
        if (e) {
            const p = carrito[+e.dataset.eliminar];
            if (!confirm("¿Quitar «" + p.nombre + "» del carrito?")) return;
            carrito.splice(+e.dataset.eliminar, 1);
            UI.guardar("carrito", carrito);
            mostrarToast("Producto eliminado");
            cargar();
        }
    });

    document.getElementById("btnVaciar").addEventListener("click", () => {
        if (!confirm("¿Vaciar todo el carrito?")) return;
        localStorage.removeItem("carrito");
        cargar();
    });

    document.getElementById("btnComprar").addEventListener("click", () => {
        if (!UI.leer("carrito", []).length) return mostrarToast("Tu carrito está vacío", "error");
        if (!Api.sesion() || !Api.token()) {
            sessionStorage.setItem("volver_a", "carrito.html");
            return Api.irALogin("Inicia sesión para finalizar tu compra.");
        }
        sessionStorage.setItem("token_compra", Date.now().toString(36) + Math.random().toString(36).slice(2, 10));
        location.href = "pago.html";
    });

    cargar();
})();
