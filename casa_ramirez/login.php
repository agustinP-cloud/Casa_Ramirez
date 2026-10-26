<?php
require_once 'includes/auth.php';

if (isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = $_POST['clave'] ?? '';

    $st = $pdo->prepare("SELECT u.id_usuario, u.nombre, u.clave_hash, u.id_sucursal, r.nombre AS rol
                         FROM usuario u
                         INNER JOIN rol r ON r.id_rol = u.id_rol
                         WHERE u.usuario = ?");
    $st->execute([$usuario]);
    $u = $st->fetch();

    if ($u && password_verify($clave, $u['clave_hash'])) {
        session_regenerate_id(true);
        $_SESSION['id_usuario']  = $u['id_usuario'];
        $_SESSION['nombre']      = $u['nombre'];
        $_SESSION['rol']         = $u['rol'];
        $_SESSION['id_sucursal'] = $u['id_sucursal'];
        header('Location: index.php');
        exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar - Casa Ramírez</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<div class="login tarjeta">
    <h1>Casa Ramírez</h1>
    <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <label>Usuario</label>
        <input type="text" name="usuario" required autofocus>
        <br><br>
        <label>Contraseña</label>
        <input type="password" name="clave" required>
        <br><br>
        <button type="submit">Ingresar</button>
    </form>
</div>
</body>
</html>