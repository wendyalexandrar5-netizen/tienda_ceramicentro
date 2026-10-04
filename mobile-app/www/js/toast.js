/** Mensajes breves (toast) accesibles. */
function mostrarToast(mensaje, tipo) {
    const viejo = document.querySelector(".toast");
    if (viejo) viejo.remove();
    const toast = document.createElement("div");
    toast.className = "toast" + (tipo ? " toast-" + tipo : "");
    toast.setAttribute("role", tipo === "error" ? "alert" : "status");
    toast.setAttribute("aria-live", "polite");
    toast.textContent = mensaje;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add("show"), 50);
    setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => toast.remove(), 300);
    }, tipo === "error" ? 4500 : 2800);
}
