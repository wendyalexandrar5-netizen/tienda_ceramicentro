/** Productos favoritos (guardados en el dispositivo). */
(function () {
    "use strict";
    const lista = document.getElementById("listaFavoritos");

    function cargar() {
        const favs = UI.leer("favoritos", []);
        if (!favs.length) {
            UI.estado(lista, { icono: "🤍", titulo: "No tienes favoritos todavía", texto: "Toca «Agregar a favoritos» en el detalle de un producto.", boton: "Ver productos", accion: () => location.href = "../home.html" });
            return;
        }
        lista.innerHTML = favs.map(p => '<article class="producto-card">' + UI.img(p.imagen, p.nombre, "producto-img") +
            "<h3>" + UI.esc(p.nombre) + '</h3><strong class="precio">' + UI.precio(p.precio) + "</strong>" +
            '<button type="button" class="btn-secundario-app" data-ver="' + UI.esc(p.id) + '">Ver detalle</button>' +
            '<button type="button" class="btn-texto" data-quitar="' + UI.esc(p.id) + '">Quitar de favoritos</button></article>').join("");
    }

    lista.addEventListener("click", ev => {
        const favs = UI.leer("favoritos", []);
        const ver = ev.target.closest("[data-ver]");
        if (ver) {
            localStorage.setItem("productoDetalle", JSON.stringify(favs.find(p => p.id === ver.dataset.ver)));
            location.href = "detalle.html";
        }
        const q = ev.target.closest("[data-quitar]");
        if (q) {
            UI.guardar("favoritos", favs.filter(p => p.id !== q.dataset.quitar));
            mostrarToast("Favorito eliminado");
            cargar();
        }
    });
    cargar();
})();
