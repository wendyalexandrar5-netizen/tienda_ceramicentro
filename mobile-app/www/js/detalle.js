const contenedor = document.getElementById("detalleProducto");
const producto = JSON.parse(localStorage.getItem("productoDetalle"));

if(!producto){
    contenedor.innerHTML = `<p>Producto no encontrado</p>`;
}else{
    renderDetalle();
}

function renderDetalle(){

    const favorito = esFavorito(producto.id);

    contenedor.innerHTML = `
        <div class="detalle-card">

            <img src="${producto.imagenFinal || producto.imagen}" class="detalle-img">

            <div class="detalle-info">

                <p class="detalle-categoria">
                    ${producto.categoria || "Sin categoría"}
                </p>

                <h2>${producto.nombre}</h2>

                <p class="detalle-descripcion">
                    ${producto.descripcion}
                </p>

                <h3>$${producto.precio}</h3>

                <div class="cantidad-box">
                    <input
                        type="number"
                        id="cantidadDetalle"
                        min="1"
                        max="${producto.stock}"
                        value="1"
                    >
                </div>

                <button id="btnAgregarCarrito">
                    🛒 Agregar al carrito
                </button>

                <button id="btnFavoritoDetalle" class="btn-favorito-detalle">
                    ${favorito ? "❤️ Quitar de favoritos" : "🤍 Agregar a favoritos"}
                </button>

            </div>
        </div>
    `;

    document
    .getElementById("btnFavoritoDetalle")
    .addEventListener("click", toggleFavoritoDetalle);

    document
    .getElementById("btnAgregarCarrito")
    .addEventListener("click", agregarCarritoDetalle);
}

function toggleFavoritoDetalle(){

    let favoritos =
    JSON.parse(
        localStorage.getItem("favoritos")
    ) || [];

    const existe =
    favoritos.some(
        p => p.id === producto.id
    );

    const btn =
    document.getElementById(
        "btnFavoritoDetalle"
    );

    if(existe){

        favoritos =
        favoritos.filter(
            p => p.id !== producto.id
        );

        btn.innerText =
        "🤍 Agregar a favoritos";

        mostrarToast(
            "Producto eliminado de favoritos"
        );

    }else{

        favoritos.push(producto);

        btn.innerText =
        "❤️ Quitar de favoritos";

        mostrarToast(
            "Producto agregado a favoritos"
        );
    }

    localStorage.setItem(
        "favoritos",
        JSON.stringify(favoritos)
    );
}

function esFavorito(idProducto){

    const favoritos = JSON.parse(localStorage.getItem("favoritos")) || [];

    return favoritos.some(p => p.id === idProducto);
}

function agregarCarritoDetalle(){

    const cantidad = parseInt(
        document.getElementById("cantidadDetalle").value
    ) || 1;

    let carrito = JSON.parse(localStorage.getItem("carrito")) || [];

    carrito.push({
        ...producto,
        cantidad
    });

    localStorage.setItem("carrito", JSON.stringify(carrito));

    mostrarToast("Producto agregado al carrito");
}
