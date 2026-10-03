window.open("https://registro.pse.com.co/PSEUserRegister/", "_blank");

document.getElementById("estadoPago").innerText =
    "Te redirigimos a PSE. Espera mientras confirmamos el pago...";

setTimeout(async ()=>{

    const usuario = JSON.parse(localStorage.getItem("usuario"));
    const carrito = JSON.parse(localStorage.getItem("carrito")) || [];

    if(!usuario || carrito.length === 0){
        alert("No hay datos para procesar el pago");
        window.location.href = "../index.html";
        return;
    }

    try{

        const respuesta = await Capacitor.Plugins.CapacitorHttp.post({
            url: "http://172.20.10.4/tiendaonline_mongodb/api/api_crear_pedido.php",
            headers:{
                "Content-Type":"application/json"
            },
            data:{
                usuario,
                carrito
            }
        });

        const data = typeof respuesta.data === "string"
            ? JSON.parse(respuesta.data)
            : respuesta.data;

        if(data.success){

            localStorage.removeItem("carrito");

            localStorage.setItem("ultimoPedido", JSON.stringify(data));

            window.location.href = "pago_exitoso.html";

        }else{
            mostrarToast(data.message);
        }

    }catch(error){
        mostrarToast("Error procesando el pago");
    }

}, 4000);
