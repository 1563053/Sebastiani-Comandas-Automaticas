<?php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();
require_once("conexion.php");
if (!isset($_SESSION['intentos'])) {
    $_SESSION['intentos'] = 0;
}
if (!isset($_SESSION['bloqueo'])) {
    $_SESSION['bloqueo'] = 0;
}
$mensaje = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($_SESSION['bloqueo'] > time()) {
        $segundos = $_SESSION['bloqueo'] - time();
        $mensaje = "Demasiados intentos. Espere $segundos segundos.";
    } else {
        $usuario = trim($_POST['usuario']);
        $clave   = trim($_POST['password']);
        if (empty($usuario) || empty($clave)) {
            $mensaje = "Por favor, complete todos los campos.";
        } else {
            $sql = "SELECT nombre, rol, clave FROM usuario WHERE nombre = ? AND estado = 'activo'";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $resultado = $stmt->get_result();
            if ($resultado->num_rows === 1) {
                $row = $resultado->fetch_assoc();
                if (password_verify($clave, $row['clave'])) {
                    $_SESSION['intentos'] = 0;
                    $_SESSION['bloqueo'] = 0;
                    session_regenerate_id(true);
                    $_SESSION['usuario'] = $row['nombre'];
                    $_SESSION['rol']     = $row['rol'];
                    switch ($row['rol']) {
                        case 'mesa':
                            header("Location: mesas.php");
                            break;
                        case 'caja':
                            header("Location: caja.php");
                            break;
                        case 'cocina':
                            header("Location: cocina.php");
                            break;
                    }
                    exit();
                } else {
                    $_SESSION['intentos']++;
                    if ($_SESSION['intentos'] >= 5) {
                        $_SESSION['bloqueo'] = time() + 300;
                    }
                    $mensaje = "Credenciales incorrectas";
                }
            } else {
                $_SESSION['intentos']++;
                if ($_SESSION['intentos'] >= 5) {
                    $_SESSION['bloqueo'] = time() + 300;
                }
                $mensaje = "Credenciales incorrectas";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Sebatiani POS</title>
        <link rel="stylesheet" href="src/output.css">
        <link rel="stylesheet" href="src/style.css">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>

    <body class="min-h-dvh flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <div class="card-modern shadow-soft bg-white p-8">
                <div class="flex justify-center mb-6">
                    <img class="w-16 h-16 flex items-center justify-center rounded-full" src="img/SebastianiLogo1.png">
                </div>
                <h1 class="text-2xl text-center font-head mb-2">
                    Bienvenido
                </h1>
                <p class="text-center text-sm mb-6" style="color: var(--color-text-muted);">
                    Ingresa tus credenciales para continuar
                </p>

                <?php if ($mensaje): ?>
                    <div class="mb-4 px-4 py-3 rounded-lg text-sm flex items-center gap-2"
                        style="background-color: rgba(168,50,50,0.1); color: var(--color-primary);">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off" class="space-y-4">
                    <div>
                        <label class="block text-sm mb-1">Usuario</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fa-solid fa-user"></i>
                            </span>
                            <input type="text" name="usuario" required
                                class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2"
                                style="focus:ring-color: var(--color-primary);">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Contraseña</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" name="password" required
                                class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2"
                                style="focus:ring-color: var(--color-primary);">
                        </div>
                    </div>
                    <button type="submit"
                            class="w-full py-3 btn-pill text-white font-semibold transition"
                            style="background-color: var(--color-primary);">
                        Ingresar
                    </button>
                </form>
            </div>
        </div>
    </body>
</html>
