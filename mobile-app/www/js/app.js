/** Pantalla de inicio: catálogo, búsqueda y categorías. */
(function () {
    "use strict";
    const usuario = Api.sesion();
    const saludo = document.getElementById("saludoUsuario");
    const btnIngresar = document.getElementById("btnIngresar");
    const grid = document.getElementById("productosGrid");
    const buscador = document.getElementById("buscadorProductos");
    const contCategorias = document.getElementById("categoriasContainer");
    const resumen = document.getElementById("resumenResultados");

    let productos = [];
    let categoriaActual = "Todas";

    if (usuario && Api.token()) {
        saludo.textContent = "Hola, " + (usuario.nombre || "").split(" ")[0];
        btnIngresar.hidden = true;
    }

    async function cargar() {
        UI.esqueletos(grid, 4);
        resumen.textContent = "";
        try {
            const [lista, cats] = await Promise.all([
                Api.get("api_productos.php"),
                Api.get("api_categorias.php").catch(() => ({ categorias: [] }))
            ]);
            productos = Array.isArray(lista) ? lista : [];
            sessionStorage.setItem("productos_cache", JSON.stringify(productos));
            renderCategorias(cats.categorias || []);
            aplicarFiltros();
        } catch (error) {
            console.error("Error cargando catálogo:", error);
            // Si hay datos guardados de una carga anterior, se muestran mientras tanto
            const cache = JSON.parse(sessionStorage.getItem("productos_cache") || "null");
            if (cache && cache.length) {
                productos = cache;
                aplicarFiltros();
                mostrarToast("Mostrando datos guardados: " + error.message, "error");
            } else {
                UI.mostrarError(grid, error, cargar);
            }
        }
    }

    function renderCategorias(categorias) {
        const nombres = ["Todas"].concat(categorias.map(c => c.nombre));
        contCategorias.innerHTML = nombres.map(n =>
            '<button type="button" class="categoria-btn' + (n === categoriaActual ? " active" : "") + '" aria-pressed="' + (n === categoriaActual) + '" data-categoria="' + UI.esc(n) + '">' + UI.esc(n) + "</button>"
        ).join("");
    }

    contCategorias.addEventListener("click", ev => {
        const b = ev.target.closest("[data-categoria]");
        if (!b) return;
        categoriaActual = b.dataset.categoria;
        contCategorias.querySelectorAll(".categoria-btn").forEach(x => {
            const activo = x.dataset.categoria === categoriaActual;
            x.classList.toggle("active", activo);
            x.setAttribute("aria-pressed", activo);
        });
        aplicarFiltros();
    });

    function aplicarFiltros() {
        const texto = buscador.value.trim().toLowerCase();
        const filtrados = productos.filter(p =>
            (categoriaActual === "Todas" || p.categoria === categoriaActual) &&
            (!texto || (p.nombre || "").toLowerCase().includes(texto) || (p.descripcion || "").toLowerCase().includes(texto))
        );
        render(filtrados);
        resumen.textContent = filtrados.length + " producto" + (filtrados.length === 1 ? "" : "s") + (texto ? " para «" + buscador.value.trim() + "»" : "");
    }

    function render(lista) {
        if (!lista.length) {
            UI.estado(grid, { icono: "🔍", titulo: "No encontramos productos", texto: "Prueba con otra palabra o categoría.", boton: "Ver todos", accion: () => { buscador.value = ""; categoriaActual = "Todas"; contCategorias.querySelector("[data-categoria]")?.click(); } });
            return;
        }
        grid.innerHTML = lista.map(p => {
            const agotado = p.stock <= 0;
            return '<article class="producto-card">' +
                '<button type="button" class="tarjeta-enlace" data-detalle="' + UI.esc(p.id) + '" aria-label="Ver detalle de ' + UI.esc(p.nombre) + '">' +
                UI.img(p.imagen, p.nombre, "producto-img") + "</button>" +
                '<p class="categoria-texto">' + UI.esc(p.categoria || "Sin categoría") + "</p>" +
                "<h3>" + UI.esc(p.nombre) + "</h3>" +
                '<p class="descripcion-corta">' + UI.esc(p.descripcion) + "</p>" +
                (agotado ? '<p class="stock-bajo">Agotado</p>' : p.stock <= 5 ? '<p class="stock-bajo">Últimas ' + p.stock + " unidades</p>" : "") +
                '<strong class="precio">' + UI.precio(p.precio) + "</strong>" +
                '<button type="button" class="btn-secundario-app" data-detalle="' + UI.esc(p.id) + '">Ver detalle</button>' +
                (agotado ? "" :
                    '<div class="cantidad-box"><label class="sr-only" for="cant-' + UI.esc(p.id) + '">Cantidad</label>' +
                    '<input type="number" id="cant-' + UI.esc(p.id) + '" min="1" max="' + p.stock + '" value="1" inputmode="numeric">' +
                    '<button type="button" class="btn-principal" data-agregar="' + UI.esc(p.id) + '">Agregar</button></div>') +
                "</article>";
        }).join("");
    }

    grid.addEventListener("click", ev => {
        const det = ev.target.closest("[data-detalle]");
        if (det) {
            const p = productos.find(x => x.id === det.dataset.detalle);
            if (p) {
                localStorage.setItem("productoDetalle", JSON.stringify(p));
                location.href = "pages/detalle.html";
            }
            return;
        }
        const add = ev.target.closest("[data-agregar]");
        if (add) {
            const p = productos.find(x => x.id === add.dataset.agregar);
            const cant = document.getElementById("cant-" + p.id).value;
            const r = UI.agregarAlCarrito(p, cant);
            mostrarToast(r.mensaje, r.ok ? "ok" : "error");
        }
    });

    let espera;
    buscador.addEventListener("input", () => {
        clearTimeout(espera);
        espera = setTimeout(aplicarFiltros, 200);
    });

    cargar();
})();
