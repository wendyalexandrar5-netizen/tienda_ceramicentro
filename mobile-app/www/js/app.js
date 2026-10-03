const usuario = JSON.parse(localStorage.getItem("usuario"));

const saludo = document.getElementById("saludoUsuario");
const btnLogout = document.getElementById("btnLogout");

if(usuario){
    saludo.innerText = "Hola, " + usuario.nombre;
    btnLogout.style.display = "block";
}else{
    saludo.innerText = "Hola, invitado";
}

btnLogout.addEventListener("click", ()=>{
    localStorage.removeItem("usuario");
    window.location.href = "pages/login.html";
});

const grid = document.getElementById("productosGrid");
const buscador = document.getElementById("buscadorProductos");
const categoriasContainer = document.getElementById("categoriasContainer");

let categoriaActual = "Todas";

async function imagenABase64(url){

    const res = await Capacitor.Plugins.CapacitorHttp.get({
        url: url,
        responseType: "blob"
    });

    return `data:image/jpeg;base64,${res.data}`;
}

async function cargarProductos(){

    grid.innerHTML = `
    <div class="loading"></div>
    `;

    try{

        const respuesta = await Capacitor.Plugins.CapacitorHttp.get({
            url: "http://172.20.10.4/tiendaonline_mongodb/api/api_productos.php",
            headers: {
                "Accept": "application/json"
            }
        });

        const productos = typeof respuesta.data === "string"
            ? JSON.parse(respuesta.data)
            : respuesta.data;

        window.productosGlobales = [];
        window.todosProductos = [];

        for(const producto of productos){

            let imagenFinal = producto.imagen;

            try{
                imagenFinal = await imagenABase64(producto.imagen);
            }catch(e){
                imagenFinal = "https://via.placeholder.com/300x200?text=Sin+imagen";
            }

            const productoCompleto = {
                ...producto,
                imagenFinal: imagenFinal
            };

            window.productosGlobales.push(productoCompleto);
            window.todosProductos.push(productoCompleto);
        }

        renderCategorias();
        aplicarFiltros();

    }catch(error){

        console.error("ERROR REAL:", error);

        grid.innerHTML = `
            <p>Error cargando productos</p>
            <small>${error.message}</small>
        `;
    }
}

async function renderCategorias(){

    try{

        const respuesta = await Capacitor.Plugins.CapacitorHttp.get({
            url: "http://172.20.10.4/tiendaonline_mongodb/api/api_categorias.php"
        });

        const data = typeof respuesta.data === "string"
            ? JSON.parse(respuesta.data)
            : respuesta.data;

        if(!data.success){
            return;
        }

        const categorias = [
            "Todas",
            ...data.categorias.map(c => c.nombre)
        ];

        categoriasContainer.innerHTML = "";

        categorias.forEach(cat => {

            categoriasContainer.innerHTML += `
                <button
                    class="categoria-btn ${cat === categoriaActual ? "active" : ""}"
                    onclick="filtrarCategoria('${cat}')"
                >
                    ${cat}
                </button>
            `;
        });

    }catch(error){
        console.error("Error cargando categorías", error);
    }
}

function renderProductos(productos){

    grid.innerHTML = "";

    if(productos.length === 0){
        grid.innerHTML = "<p>No se encontraron productos.</p>";
        return;
    }

    productos.forEach(producto => {

        grid.innerHTML += `
            <div class="card producto-card">

                <img
                    src="${producto.imagenFinal}"
                    alt="${producto.nombre}"
                    class="producto-img"
                >

                <h3>${producto.nombre}</h3>

                <p>${producto.descripcion}</p>

                <p class="categoria-texto">
                    ${producto.categoria || "Sin categoría"}
                </p>

                ${
                    producto.stock <= 5
                    ? `<p class="stock-bajo">
                        Últimas ${producto.stock} unidades
                        </p>`
                : ""
                }

                <strong>
                    $${producto.precio}
                </strong>

                <button onclick="verDetalle('${producto.id}')">
                Ver detalle
                </button>

                <div class="cantidad-box">

                    <input
                        type="number"
                        id="cantidad-${producto.id}"
                        min="1"
                        max="${producto.stock}"
                        value="1"
                    >

                    <button onclick="agregarCarrito('${producto.id}')">
                        Agregar
                    </button>

                </div>

            </div>
        `;
    });
}

function filtrarCategoria(categoria){

    categoriaActual = categoria;

    renderCategorias();

    aplicarFiltros();
}

function aplicarFiltros(){

    const texto = buscador.value.toLowerCase();

    const filtrados = window.todosProductos.filter(producto => {

        const coincideTexto =
            producto.nombre.toLowerCase().includes(texto) ||
            producto.descripcion.toLowerCase().includes(texto);

        const coincideCategoria =
            categoriaActual === "Todas" ||
            producto.categoria === categoriaActual;

        return coincideTexto && coincideCategoria;
    });

    renderProductos(filtrados);
}

buscador.addEventListener("input", ()=>{
    aplicarFiltros();
});

cargarProductos();

function agregarCarrito(idProducto){

    const producto = window.productosGlobales.find(
        p => p.id === idProducto
    );

    if(!producto){
        mostrarToast("Producto no encontrado");
        return;
    }

    const cantidadInput = document.getElementById("cantidad-" + idProducto);
    const cantidad = parseInt(cantidadInput.value) || 1;

    if(cantidad <= 0){
        mostrarToast("Cantidad inválida");
        return;
    }

    if(cantidad > producto.stock){
        mostrarToast("Solo hay " + producto.stock + " unidades disponibles");
        return;
    }

    let carrito = JSON.parse(localStorage.getItem("carrito")) || [];

    const existente = carrito.find(
        p => p.id === producto.id
    );

    const cantidadEnCarrito = existente
        ? Number(existente.cantidad)
        : 0;

    if(cantidad + cantidadEnCarrito > producto.stock){
        mostrarToast("No puedes agregar más del stock disponible");
        return;
    }

    if(existente){
        existente.cantidad = cantidadEnCarrito + cantidad;
    }else{
        carrito.push({
            ...producto,
            cantidad: cantidad
        });
    }

    localStorage.setItem("carrito", JSON.stringify(carrito));

    mostrarToast(producto.nombre + " agregado x" + cantidad);
}

function toggleFavorito(idProducto){

    const producto = window.productosGlobales.find(
        p => p.id === idProducto
    );

    let favoritos = JSON.parse(localStorage.getItem("favoritos")) || [];

    const existe = favoritos.find(p => p.id === idProducto);

    if(existe){
        favoritos = favoritos.filter(p => p.id !== idProducto);
        mostrarToast("Producto eliminado de favoritos");
    }else{
        favoritos.push(producto);
        mostrarToast("Producto agregado a favoritos");
    }

    localStorage.setItem("favoritos", JSON.stringify(favoritos));
}

function esFavorito(idProducto){

    const favoritos =
    JSON.parse(
        localStorage.getItem("favoritos")
    ) || [];

    return favoritos.some(
        p => p.id === idProducto
    );
}

function verDetalle(idProducto){

    const producto = window.productosGlobales.find(
        p => p.id === idProducto
    );

    localStorage.setItem(
        "productoDetalle",
        JSON.stringify(producto)
    );

    window.location.href = "pages/detalle.html";
}
