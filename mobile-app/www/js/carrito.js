const lista = document.getElementById("listaCarrito");

const totalTexto = document.getElementById("totalCarrito");

function cargarCarrito(){

    const carrito = JSON.parse(
        localStorage.getItem("carrito")
    ) || [];

    lista.innerHTML = "";

    let total = 0;

    if(carrito.length === 0){

        lista.innerHTML = `
            <p>Tu carrito está vacío.</p>
        `;

        totalTexto.innerText = "0";

        return;
    }

    carrito.forEach((producto,index)=>{

        const cantidad =
        Number(producto.cantidad) || 1;

        const subtotal =
        Number(producto.precio) * cantidad;

        total += subtotal;

        lista.innerHTML += `

            <div class="card producto-card">

                <img
                    src="${producto.imagenFinal}"
                    class="producto-img"
                    alt="${producto.nombre}"
                >

                <h3>
                    ${producto.nombre}
                </h3>

                <p>
                    ${producto.descripcion}
                </p>

                <p>
                    Cantidad:
                    ${cantidad}
                </p>

                <strong>
                    Subtotal:
                    $${subtotal.toFixed(2)}
                </strong>

                <button onclick="eliminarProducto(${index})">

                    Eliminar

                </button>

            </div>
        `;
    });

    totalTexto.innerText =
    total.toFixed(2);
}

function eliminarProducto(index){

    let carrito = JSON.parse(
        localStorage.getItem("carrito")
    ) || [];

    carrito.splice(index,1);

    localStorage.setItem(
        "carrito",
        JSON.stringify(carrito)
    );

    cargarCarrito();
}

function vaciarCarrito(){

    localStorage.removeItem("carrito");

    cargarCarrito();
}

document.getElementById("btnComprar")
.addEventListener("click", ()=>{

    const usuario = JSON.parse(
        localStorage.getItem("usuario")
    );

    const carrito = JSON.parse(
        localStorage.getItem("carrito")
    ) || [];

    if(!usuario){

        mostrarToast("Debes iniciar sesión");

        return;
    }

    if(carrito.length === 0){

        mostrarToast("Tu carrito está vacío");

        return;
    }

    window.location.href = "pago.html";
});

cargarCarrito();
