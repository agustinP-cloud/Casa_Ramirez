<?php
require_once 'includes/auth.php';
requerir_login();
$suc = $_SESSION['id_sucursal'];

$total = $pdo->query("SELECT COUNT(*) FROM producto WHERE activo = 1")->fetchColumn();

$st = $pdo->prepare("SELECT p.nombre, s.cantidad, s.stock_minimo
                     FROM stock s
                     INNER JOIN producto p ON p.id_producto = s.id_producto
                     WHERE s.id_sucursal = ? AND p.activo = 1 AND s.cantidad <= s.stock_minimo
                     ORDER BY p.nombre");
$st->execute([$suc]);
$bajos = $st->fetchAll();

require 'includes/header.php';
?>
<h1>Inicio</h1>

<div class="tarjetas">
    <div class="tarjeta">
        <div>Productos activos</div>
        <div class="numero"><?= (int)$total ?></div>
    </div>
    <div class="tarjeta">
        <div>Productos con stock bajo</div>
        <div class="numero"><?= count($bajos) ?></div>
    </div>
</div>

<div class="tarjeta">
    <h3>Para reponer</h3>
    <?php if (!$bajos): ?>
        <p>No hay productos con stock bajo.</p>
    <?php else: ?>
        <table>
            <tr><th>Producto</th><th>Stock actual</th><th>Mínimo</th></tr>
            <?php foreach ($bajos as $b): ?>
            <tr class="bajo">
                <td><?= e($b['nombre']) ?></td>
                <td><?= e($b['cantidad']) ?></td>
                <td><?= e($b['stock_minimo']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
<?php require 'includes/footer.php'; ?>