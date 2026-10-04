/** Historial de pedidos del cliente. */
(function () {
    "use strict";
    const lista = document.getElementById("listaPedidos");
    let pedidos = [];

    function claseEstado(e) {
        if (e === "Pagado" || e === "Entregado") return "estado-ok";
        if (e === "En preparación" || e === "Enviado") return "estado-proceso";
        if (e === "Pendiente de pago") return "estado-pendiente";
        if (e === "Cancelado" || e === "Pago rechazado") return "estado-error";
        return "estado-neutro";
    }

    async function cargar() {
        if (!Api.requerirSesion()) return;
        lista.innerHTML = '<div class="loading" role="status" aria-label="Cargando pedidos"></div>';
        try {
            const data = await Api.post("api_pedidos.php", {});
            pedidos = data.pedidos || [];
            if (!pedidos.length) {
                UI.estado(lista, { icono: "📦", titulo: "Aún no tienes pedidos", texto: "Cuando compres, aquí verás el estado de cada pedido.", boton: "Ir a comprar", accion: () => location.href = "../home.html" });
                return;
            }
            lista.innerHTML = pedidos.map(p =>
                '<article class="pedido-card"><div class="pedido-cabecera"><div><h2>Pedido ' + UI.esc(p.numero || p.id) + "</h2>" +
                '<p class="nota">' + UI.esc(p.fecha) + "</p></div>" +
                '<span class="estado-badge ' + claseEstado(p.estado) + '">' + UI.esc(p.estado) + "</span></div>" +
                (p.explicacion ? '<p class="nota">' + UI.esc(p.explicacion) + "</p>" : "") +
                '<details><summary>' + p.productos.length + " producto(s) · " + UI.precio(p.total) + "</summary>" +
                '<div class="pedido-lista">' + p.productos.map(x =>
                    '<div class="pedido-producto">' + UI.img(x.imagen, x.nombre, "") +
                    "<div><h3>" + UI.esc(x.nombre) + "</h3><p>Cantidad: " + x.cantidad + "</p><p>Precio: " + UI.precio(x.precio) + "</p>" +
                    "<strong>Subtotal: " + UI.precio(x.subtotal) + "</strong></div></div>").join("") + "</div></details>" +
                '<p class="fila-total"><span>Total</span><strong>' + UI.precio(p.total) + "</strong></p>" +
                '<div class="pedido-actions"><button type="button" class="btn-secundario-app" data-repetir="' + UI.esc(p.id) + '">🔁 Repetir</button>' +
                '<button type="button" class="btn-pdf" data-pdf="' + UI.esc(p.id) + '">📄 Comprobante</button></div></article>'
            ).join("");
        } catch (error) {
            UI.mostrarError(lista, error, cargar);
        }
    }

    lista.addEventListener("click", ev => {
        const pdf = ev.target.closest("[data-pdf]");
        if (pdf) {
            const p = pedidos.find(x => x.id === pdf.dataset.pdf);
            if (p && p.comprobante_url) window.open(p.comprobante_url, "_blank");
            else mostrarToast("No se pudo generar el enlace del comprobante. Recarga la pantalla.", "error");
        }
        const rep = ev.target.closest("[data-repetir]");
        if (rep) {
            const p = pedidos.find(x => x.id === rep.dataset.repetir);
            const productos = JSON.parse(sessionStorage.getItem("productos_cache") || "[]");
            let agregados = 0;
            const avisos = [];
            p.productos.forEach(x => {
                const actual = productos.find(y => y.id === x.producto_id) ||
                    { id: x.producto_id, nombre: x.nombre, precio: x.precio, stock: x.cantidad, imagen: x.imagen, descripcion: "" };
                const r = UI.agregarAlCarrito(actual, x.cantidad);
                if (r.ok) agregados++; else avisos.push(x.nombre + ": " + r.mensaje);
            });
            if (avisos.length) mostrarToast(avisos[0], "error");
            if (agregados) {
                mostrarToast("Se agregaron " + agregados + " producto(s) al carrito", "ok");
                setTimeout(() => location.href = "carrito.html", 900);
            }
        }
    });

    cargar();
})();
