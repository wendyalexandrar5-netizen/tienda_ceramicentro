/** Confirmación del pedido después del pago simulado. */
(function () {
    "use strict";
    const pedido = UI.leer("ultimoPedido", null);
    if (!pedido || !pedido.pedido_id) {
        location.replace("pedidos.html");
        return;
    }
    document.getElementById("pedidoNumero").textContent = pedido.numero || pedido.pedido_id;
    document.getElementById("pedidoFecha").textContent = pedido.fecha || "—";
    document.getElementById("pedidoEstado").textContent = pedido.estado || "Pagado";
    document.getElementById("pedidoRef").textContent = pedido.referencia || "—";
    document.getElementById("totalPedido").textContent = UI.precio(pedido.total);
    document.getElementById("btnComprobante").addEventListener("click", () => {
        if (!pedido.comprobante_url) return mostrarToast("Abre el comprobante desde «Mis pedidos»", "error");
        window.open(pedido.comprobante_url, "_blank");
    });
})();
