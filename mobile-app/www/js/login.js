const form = document.getElementById("loginForm");

form.addEventListener("submit", async (e)=>{

    e.preventDefault();

    const correo = document.getElementById("correo").value;
    const password = document.getElementById("password").value;

    try{

        const respuesta = await Capacitor.Plugins.CapacitorHttp.post({

            url: "http://172.20.10.4/tiendaonline_mongodb/api/api_login.php",

            headers: {
                "Content-Type":"application/json"
            },

            data: {
                correo,
                password
            }
        });

        const data = typeof respuesta.data === "string"
            ? JSON.parse(respuesta.data)
            : respuesta.data;

        if(data.success){

            localStorage.setItem(
                "usuario",
                JSON.stringify(data.usuario)
            );

            window.location.href = "../index.html";

        }else{

            document.getElementById("mensaje")
            .innerText = data.message;
        }

    }catch(error){

        mostrarToast("Error: " + error.message);
console.log("ERROR LOGIN:", error);
    }
});
