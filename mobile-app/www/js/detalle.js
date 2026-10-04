/** Detalle de un producto. */
(function () {
    "use strict";
    const cont = document.getElementById("detalleProducto");
    let producto = UI.leer("productoDetalle", null);

    if (!producto || !producto.id) {
        UI.estado(cont, { icono: "🔍", titulo: "Producto no encontrado", texto: "Vuelve al catálogo para elegir un producto.", boton: "Ir al catálogo", accion: () => location.href = "../home.html" });
        return;
    }

    function esFavorito() { return UI.leer("favoritos", []).some(p => p.id === producto.id); }

    function render() {
        const agotado = producto.stock <= 0;
        cont.innerHTML = '<article class="detalle-card">' + UI.img(producto.imagen, producto.nombre, "detalle-img") +
            '<div class="detalle-info">' +
            '<p class="detalle-categoria">' + UI.esc(producto.categoria || "Sin categoría") + "</p>" +
            "<h2>" + UI.esc(producto.nombre) + "</h2>" +
            '<p class="detalle-descripcion">' + UI.esc(producto.descripcion) + "</p>" +
            '<p class="precio-detalle">' + UI.precio(producto.precio) + ' <small>IVA incluido</small></p>' +
            (agotado ? '<p class="stock-bajo">Agotado por ahora</p>' : '<p class="stock-ok">' + producto.stock + " unidades disponibles</p>" +
                '<div class="cantidad-box"><button type="button" class="btn-cant" data-cambio="-1" aria-label="Menos">−</button>' +
                '<label class="sr-only" for="cantidadDetalle">Cantidad</label><input type="number" id="cantidadDetalle" min="1" max="' + producto.stock + '" value="1" inputmode="numeric">' +
                '<button type="button" class="btn-cant" data-cambio="1" aria-label="Más">+</button></div>' +
                '<button type="button" id="btnAgregarCarrito" class="btn-principal">🛒 Agregar al carrito</button>') +
            '<button type="button" id="btnFavoritoDetalle" class="btn-favorito-detalle" aria-pressed="' + esFavorito() + '">' + (esFavorito() ? "❤️ Quitar de favoritos" : "🤍 Agregar a favoritos") + "</button>" +
            "</div></article>";
    }
    render();

    cont.addEventListener("click", ev => {
        const c = ev.target.closest("[data-cambio]");
        if (c) {
            const input = document.getElementById("cantidadDetalle");
            input.value = Math.min(producto.stock, Math.max(1, (parseInt(input.value, 10) || 1) + Number(c.dataset.cambio)));
        }
        if (ev.target.closest("#btnAgregarCarrito")) {
            const r = UI.agregarAlCarrito(producto, document.getElementById("cantidadDetalle").value);
            mostrarToast(r.mensaje, r.ok ? "ok" : "error");
        }
        if (ev.target.closest("#btnFavoritoDetalle")) {
            let favs = UI.leer("favoritos", []);
            if (esFavorito()) {
                favs = favs.filter(p => p.id !== producto.id);
                mostrarToast("Eliminado de favoritos");
            } else {
                favs.push(producto);
                mostrarToast("Agregado a favoritos", "ok");
            }
            UI.guardar("favoritos", favs);
            const b = document.getElementById("btnFavoritoDetalle");
            b.textContent = esFavorito() ? "❤️ Quitar de favoritos" : "🤍 Agregar a favoritos";
            b.setAttribute("aria-pressed", esFavorito());
        }
    });
})();
