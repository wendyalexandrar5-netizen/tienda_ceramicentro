const listaPedidos = document.getElementById("listaPedidos");

const BASE_URL = "http://172.20.10.4/tiendaonline_mongodb";

async function imagenABase64(url){
    if(!url){
        return "https://via.placeholder.com/90x90?text=Sin+imagen";
    }

    const res = await Capacitor.Plugins.CapacitorHttp.get({
        url: url,
        responseType: "blob"
    });

    return `data:image/jpeg;base64,${res.data}`;
}

async function cargarPedidos(){

    const usuario = JSON.parse(localStorage.getItem("usuario"));

    if(!usuario){
        listaPedidos.innerHTML = "<p>Debes iniciar sesión para ver tus pedidos.</p>";
        return;
    }

    listaPedidos.innerHTML = `
        <div class="loading"></div>
    `;

    try{

        const respuesta = await Capacitor.Plugins.CapacitorHttp.post({
            url: `${BASE_URL}/api/api_pedidos.php`,
            headers:{
                "Content-Type":"application/json"
            },
            data:{
                usuario_id: usuario.id
            }
        });

        const data = typeof respuesta.data === "string"
            ? JSON.parse(respuesta.data)
            : respuesta.data;

        if(!data.success){
            listaPedidos.innerHTML = `<p>${data.message}</p>`;
            return;
        }

        if(data.pedidos.length === 0){
            listaPedidos.innerHTML = "<p>No tienes pedidos todavía.</p>";
            return;
        }

        window.pedidosGlobales = data.pedidos;

        listaPedidos.innerHTML = "";

        for(const pedido of data.pedidos){

            let productosHTML = "";

            for(const producto of pedido.productos){

                let img = producto.imagen;

                try{
                    img = await imagenABase64(producto.imagen);
                    producto.imagenBase64 = img;
                }catch(e){
                    img = "https://via.placeholder.com/90x90?text=Sin+imagen";
                    producto.imagenBase64 = img;
                }

                productosHTML += `
                    <div class="pedido-producto">
                        <img src="${img}" alt="${producto.nombre}">

                        <div>
                            <h4>${producto.nombre}</h4>
                            <p>Cantidad: ${producto.cantidad}</p>
                            <p>Precio: $${Number(producto.precio).toFixed(2)}</p>
                            <strong>Subtotal: $${Number(producto.subtotal).toFixed(2)}</strong>
                        </div>
                    </div>
                `;
            }

            listaPedidos.innerHTML += `
                <div class="pedido-card">

                    <h2>📄 Detalle del Pedido</h2>

                    <div class="pedido-info">
                        <p><strong>ID Pedido:</strong> ${pedido.id}</p>
                        <p><strong>Fecha:</strong> ${pedido.fecha}</p>
                        <p><strong>Total:</strong> $${Number(pedido.total).toFixed(2)}</p>
                        <p>
                            <strong>Estado:</strong>
                            <span class="estado-pagado">${pedido.estado}</span>
                        </p>
                    </div>

                    <h3>🛒 Productos del Pedido</h3>

                    <div class="pedido-lista">
                        ${productosHTML}
                    </div>

                    <div class="pedido-actions">
                        <button onclick="repetirPedido('${pedido.id}')">
                            🔁 Repetir pedido
                        </button>

                        <button class="btn-pdf" onclick="descargarPedido('${pedido.id}')">
                            📄 Descargar PDF
                        </button>
                    </div>

                </div>
            `;
        }

    }catch(error){
        listaPedidos.innerHTML = `
            <p>Error cargando pedidos</p>
            <small>${error.message}</small>
        `;
    }
}

function descargarPedido(idPedido){

    const usuario = JSON.parse(localStorage.getItem("usuario"));

    if(!usuario){
        mostrarToast("Debes iniciar sesión");
        return;
    }

    window.open(
        `${BASE_URL}/api/descargar_pedido_app.php?id=${idPedido}&usuario_id=${usuario.id}`,
        "_blank"
    );
}

function repetirPedido(idPedido){

    const pedido = window.pedidosGlobales.find(
        p => p.id === idPedido
    );

    if(!pedido){
        mostrarToast("Pedido no encontrado");
        return;
    }

    let carrito = JSON.parse(localStorage.getItem("carrito")) || [];

    pedido.productos.forEach(producto => {

        carrito.push({
            id: producto.producto_id,
            nombre: producto.nombre,
            precio: producto.precio,
            cantidad: producto.cantidad,
            imagenFinal: producto.imagenBase64 || producto.imagen || "",
            imagen: producto.imagen || "",
            descripcion: "Producto repetido desde pedido"
        });
    });

    localStorage.setItem("carrito", JSON.stringify(carrito));

    mostrarToast("Pedido agregado al carrito");

    setTimeout(()=>{
        window.location.href = "carrito.html";
    }, 1000);
}

cargarPedidos();
