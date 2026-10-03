<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$mensajeEnviado = false;
$errorEnvio = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'] ?? '';
    $email = $_POST['correo'] ?? '';
    $asunto = $_POST['asunto'] ?? '';
    $mensaje = $_POST['mensaje'] ?? '';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ceramicentro6@gmail.com'; 
        $mail->Password   = 'jyzznscqzbmvqcpp';        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('ceramicentro6@gmail.com', 'CERAMICENTRO Web');
        $mail->addAddress('ceramicentro6@gmail.com', 'CERAMICENTRO Admin');

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = "
            <h3>Nuevo mensaje desde el formulario</h3>
            <p><strong>Nombre:</strong> $nombre</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Mensaje:</strong><br>$mensaje</p>
        ";

        $mail->send();
        $mensajeEnviado = true;
    } catch (Exception $e) {
        $errorEnvio = "Hubo un error al enviar el mensaje: {$mail->ErrorInfo}";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contáctanos - CERAMICENTRO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background-color: #f9fafb;
            font-family: 'Segoe UI', sans-serif;
        }
        .contact-container {
            margin: 50px auto;
        }
        .form-card {
            background-color: #fff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 0 25px rgba(0,0,0,0.05);
        }
        .header {
            text-align: center;
            color: #b71c1c;
            margin-bottom: 25px;
        }
        .btn-danger {
            background-color: #c62828;
            border: none;
        }
        .btn-danger:hover {
            background-color: #b71c1c;
        }
        .social-icons {
            text-align: center;
            margin-top: 20px;
        }
        .social-icons a {
            color: #c62828;
            margin: 0 10px;
            font-size: 28px;
            transition: color 0.3s;
        }
        .social-icons a:hover {
            color: #8e0000;
        }
        iframe {
            border-radius: 16px;
            width: 100%;
            height: 400px;
            border: none;
        }
        footer {
            text-align: center;
            padding: 20px;
            background-color: #f3f3f3;
            margin-top: 40px;
        }
        .menu {
    background-color:#c62828;
    position: fixed;
    top: 0;
    width: 100%;
    z-index: 999;
    padding: 15px 0;
}

.menu .container {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.menu .logo h1 {
    margin: 0;
    font-size: 24px;
}

.navbar ul {
    display: flex;
    gap: 20px;
}

.navbar ul li {
    list-style: none;
}

.navbar ul li a {
    color: #fff;
    padding: 10px 15px;
    transition: background 0.3s, color 0.3s;
    border-radius: 5px;
    font-weight: bold;
}

.navbar ul li a:hover {
    background-color:rgb(0, 0, 0);
    color: #fff;
}

.menu-toggle {
    display: none;
    font-size: 28px;
    color: #fff;
    cursor: pointer;
}

@media (max-width: 768px) {
    .navbar ul {
        flex-direction: column;
        background-color:  #c0392b;
        position: absolute;
        top: 60px;
        right: 0;
        width: 100%;
        display: none;
    }

    .navbar ul.active {
        display: flex;
    }

    .menu-toggle {
        display: block;
    }
}
    </style>
</head>
<body background="imagenes/fondo.jpg">
<header class="menu">
        <div class="container">
            <div class="logo">
                <h1 style="color: white;">CERAMICENTRO</h1>
            </div>
            <nav class="navbar" id="navbar">
                <ul>
                    <li><a href="index.html">Inicio</a></li>
                    <li><a href="nosotros.html">Nosotros</a></li>
                    <li><a href="productos.html">Productos</a></li>
                    <li><a href="contacto.php">Contáctanos</a></li>
                    <li><a href="login.php">Iniciar sesión</a></li>
                </ul>
            </nav>
            <div class="menu-toggle" id="menu-toggle">
                ☰
            </div>
        </div>
    </header><br><br><br>

<div class="container contact-container">
    <div class="row g-4">
        <div class="col-md-6">
            <div class="form-card">
                <h2 class="header"><i class="bi bi-envelope-fill"></i> Contáctanos</h2>

                <?php if (isset($mensajeEnviado) && $mensajeEnviado): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> ¡Gracias por contactarnos! Te responderemos pronto.</div>
                <?php elseif (isset($errorEnvio) && $errorEnvio): ?>
                    <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= $errorEnvio ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-person-fill"></i> Nombre:</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-envelope-at-fill"></i> Correo Electrónico:</label>
                        <input type="email" name="correo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-pencil-square"></i> Asunto:</label>
                        <input type="text" name="asunto" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-chat-dots-fill"></i> Mensaje:</label>
                        <textarea name="mensaje" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="tyc" id="tyc" required>
                        <label class="form-check-label checkbox-label" for="tyc">
                            Acepto los <a href="tyc.html" target="_blank">Términos y Condiciones</a>
                        </label>
                    </div>
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-danger px-4"><i class="bi bi-send-fill"></i> Enviar</button>
                        <a href="index.html" class="btn btn-secondary px-4"><i class="bi bi-house-door-fill"></i> Inicio</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3860.4610157249585!2d-90.56737862489325!3d14.629752285860048!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8589a047510e49ef%3A0xe228ca2a0dae8c94!2sCeramicentro%20Roosevelt!5e0!3m2!1ses!2sco!4v1746085696388!5m2!1ses!2sco" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>

            <div class="social-icons mt-4">
                <a href="https://facebook.com" target="_blank"><i class="bi bi-facebook"></i></a>
                <a href="https://instagram.com" target="_blank"><i class="bi bi-instagram"></i></a>
                <a href="https://wa.me/573134322830" target="_blank"><i class="bi bi-whatsapp"></i></a>
            </div>
        </div>
    </div>
</div>

<footer>
    © 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados
</footer>

</body>
</html>
