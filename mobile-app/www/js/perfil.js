const usuario = JSON.parse(
    localStorage.getItem("usuario")
);

if(!usuario){

    window.location.href =
    "login.html";

}else{

    document.getElementById("nombreUsuario")
    .innerText = usuario.nombre || "Usuario";

    document.getElementById("correoUsuario")
    .innerText = usuario.correo || "";

    document.getElementById("rolUsuario")
    .innerText = usuario.rol || "cliente";
}

document.getElementById("btnCerrarSesion")
.addEventListener("click", ()=>{

    localStorage.removeItem("usuario");

    window.location.href =
    "login.html";
});
