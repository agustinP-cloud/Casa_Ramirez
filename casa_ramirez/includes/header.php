<?php $pagina = basename($_SERVER['PHP_SELF']); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Casa Ramírez</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<div class="layout">
    <nav class="menu">
        <h2>Casa Ramírez</h2>
        <a href="index.php"     class="<?= $pagina === 'index.php' ? 'activo' : '' ?>">Inicio</a>
        <a href="productos.php" class="<?= $pagina === 'productos.php' ? 'activo' : '' ?>">Productos y stock</a>
        <!-- Las próximas etapas agregan acá: Ventas, Clientes, Compras, Reportes -->
        <div class="usuario">
            <?= e($_SESSION['nombre']) ?><br>
            <small><?= e($_SESSION['rol']) ?></small><br>
            <a href="logout.php">Cerrar sesión</a>
        </div>
    </nav>
    <main class="contenido">