const listaFavoritos =
document.getElementById("listaFavoritos");

function cargarFavoritos(){

    const favoritos =
    JSON.parse(
        localStorage.getItem("favoritos")
    ) || [];

    listaFavoritos.innerHTML = "";

    if(favoritos.length === 0){

        listaFavoritos.innerHTML = `
            <p>No tienes favoritos todavía.</p>
        `;

        return;
    }

    favoritos.forEach(producto => {

        listaFavoritos.innerHTML += `

            <div class="card producto-card">

                <img
                    src="${producto.imagenFinal || producto.imagen}"
                    class="producto-img"
                >

                <h3>
                    ${producto.nombre}
                </h3>

                <p>
                    ${producto.descripcion}
                </p>

                <strong>
                    $${producto.precio}
                </strong>

                <button
                    onclick="eliminarFavorito('${producto.id}')"
                >

                    Eliminar

                </button>

            </div>
        `;
    });
}

function eliminarFavorito(idProducto){

    let favoritos =
    JSON.parse(
        localStorage.getItem("favoritos")
    ) || [];

    favoritos =
    favoritos.filter(
        p => p.id !== idProducto
    );

    localStorage.setItem(
        "favoritos",
        JSON.stringify(favoritos)
    );

    mostrarToast(
        "Favorito eliminado"
    );

    cargarFavoritos();
}

cargarFavoritos();
