function mostrarToast(mensaje){

    const viejo =
    document.querySelector(".toast");

    if(viejo){
        viejo.remove();
    }

    const toast =
    document.createElement("div");

    toast.className = "toast";

    toast.innerText = mensaje;

    document.body.appendChild(toast);

    setTimeout(()=>{
        toast.classList.add("show");
    },100);

    setTimeout(()=>{

        toast.classList.remove("show");

        setTimeout(()=>{
            toast.remove();
        },300);

    },2500);
}
