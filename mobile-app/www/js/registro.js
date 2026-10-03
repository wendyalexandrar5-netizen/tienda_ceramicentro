const btnRegistro =
document.getElementById("btnRegistro");

const BASE_URL =
"http://172.20.10.4/tiendaonline_mongodb";

btnRegistro.addEventListener(
    "click",
    registrarUsuario
);

async function registrarUsuario(){

    const nombre =
    document.getElementById("nombre")
    .value
    .trim();

    const correo =
    document.getElementById("correo")
    .value
    .trim();

    const password =
    document.getElementById("password")
    .value
    .trim();

    if(
        nombre === "" ||
        correo === "" ||
        password === ""
    ){
        mostrarToast(
            "Completa todos los campos"
        );
        return;
    }

    btnRegistro.disabled = true;

    btnRegistro.innerText =
    "Registrando...";

    try{

        const respuesta =
        await Capacitor.Plugins.CapacitorHttp.post({

            url:
            `${BASE_URL}/api/api_registro.php`,

            headers:{
                "Content-Type":
                "application/json"
            },

            data:{
                nombre,
                correo,
                password
            }
        });

        const data =
        typeof respuesta.data === "string"
        ? JSON.parse(respuesta.data)
        : respuesta.data;

        if(data.success){

            mostrarToast(
                "Registro exitoso"
            );

            setTimeout(()=>{

                window.location.href =
                "login.html";

            },1500);

        }else{

            mostrarToast(
                data.message ||
                "Error al registrar"
            );
        }

    }catch(error){

        console.error(error);

        mostrarToast(
            "Error de conexión"
        );

    }finally{

        btnRegistro.disabled = false;

        btnRegistro.innerText =
        "Registrarme";
    }
}
