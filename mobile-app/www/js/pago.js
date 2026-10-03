/**
 * Simulador de pago PSE dentro de la app.
 * No abre sitios bancarios reales ni pide datos bancarios. Si el resultado simulado es
 * "aprobado", registra el pedido en el servidor (precios e inventario se validan allá).
 */
(function () {
    "use strict";
    const usuario = Api.requerirSesion();
    if (!usuario) return;
    const carrito = UI.leer("carrito", []);
    if (!carrito.length) {
        location.replace("carrito.html");
        return;
    }
    let token = sessionStorage.getItem("token_compra");
    if (!token) {
        token = Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
        sessionStorage.setItem("token_compra", token);
    }

    const total = carrito.reduce((s, p) => s + Number(p.precio) * (Number(p.cantidad) || 1), 0);
    const unidades = carrito.reduce((s, p) => s + (Number(p.cantidad) || 1), 0);
    document.getElementById("totalPago").textContent = UI.precio(total);
    document.getElementById("resumenPago").textContent = unidades + " unidades · " + carrito.length + " producto(s)";
    const btn = document.getElementById("btnPagar");
    btn.textContent = "Pagar " + UI.precio(total);

    const form = document.getElementById("formPse");
    const procesando = document.getElementById("procesando");
    const resultado = document.getElementById("resultadoPago");

    form.addEventListener("submit", async ev => {
        ev.preventDefault();
        if (btn.disabled) return;
        const banco = document.getElementById("banco").value;
        if (!banco) {
            mostrarToast("Selecciona un banco para continuar", "error");
            document.getElementById("banco").focus();
            return;
        }
        const persona = form.querySelector("[name=persona]:checked").value;
        const aprobado = form.querySelector("[name=resultado]:checked").value === "aprobado";

        btn.disabled = true;
        form.hidden = true;
        procesando.hidden = false;
        await new Promise(r => setTimeout(r, 1500));

        if (!aprobado) {
            procesando.hidden = true;
            UI.estado(resultado, {
                tipo: "error", icono: "❌", titulo: "Pago rechazado (simulación)",
                texto: "El banco simulado no aprobó el pago. No se realizó ningún cobro ni se creó el pedido.",
                boton: "Intentar de nuevo", accion: () => { resultado.innerHTML = ""; form.hidden = false; btn.disabled = false; }
            });
            return;
        }

        document.getElementById("textoProcesando").textContent = "Registrando tu pedido…";
        try {
            const data = await Api.post("api_crear_pedido.php", {
                carrito: carrito.map(p => ({ id: p.id, cantidad: Number(p.cantidad) || 1 })),
                token_cliente: token,
                pago: { banco, persona }
            });
            localStorage.removeItem("carrito");
            sessionStorage.removeItem("token_compra");
            localStorage.setItem("ultimoPedido", JSON.stringify(data));
            location.replace("pago_exitoso.html");
        } catch (error) {
            procesando.hidden = true;
            const esStock = error.tipo === "stock" || error.tipo === "validacion";
            UI.mostrarError(resultado, error, null);
            const acciones = document.createElement("div");
            acciones.className = "acciones-error";
            acciones.innerHTML = esStock
                ? '<a class="btn-principal enlace-boton" href="carrito.html">Revisar mi carrito</a>'
                : '<button type="button" class="btn-principal">Reintentar</button><a class="btn-texto" href="carrito.html">Volver al carrito</a>';
            resultado.appendChild(acciones);
            const reintentar = acciones.querySelector("button");
            if (reintentar) reintentar.addEventListener("click", () => { resultado.innerHTML = ""; form.hidden = false; btn.disabled = false; });
            // Si el producto ya no tiene inventario, se actualiza el stock guardado para avisar en el carrito
            if (error.tipo === "stock" && error.datos && error.datos.producto_id) {
                const c = UI.leer("carrito", []);
                const p = c.find(x => x.id === error.datos.producto_id);
                if (p) { p.stock = 0; UI.guardar("carrito", c); }
            }
        }
    });
})();
