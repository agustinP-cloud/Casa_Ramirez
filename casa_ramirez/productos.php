<?php
require_once 'includes/auth.php';
requerir_login();

$puede = in_array($_SESSION['rol'], ['dueño', 'administrativa']);
$suc   = $_SESSION['id_sucursal'];
$msg = '';
$err = '';

function num($v) { return (float) str_replace(',', '.', trim($v)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puede) {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'categoria') {
            $nombre = trim($_POST['nombre_categoria'] ?? '');
            if ($nombre === '') throw new Exception('Escribí el nombre de la categoría.');
            $pdo->prepare("INSERT INTO categoria (nombre) VALUES (?)")->execute([$nombre]);
            $msg = 'Categoría creada.';

        } elseif ($accion === 'guardar') {
            $id     = (int)($_POST['id_producto'] ?? 0);
            $cat    = (int)($_POST['id_categoria'] ?? 0);
            $codigo = trim($_POST['codigo_barras'] ?? '');
            $codigo = ($codigo === '') ? null : $codigo;
            $nombre = trim($_POST['nombre'] ?? '');
            $unidad = $_POST['unidad_venta'] ?? '';
            $costo  = num($_POST['costo']);
            $precio = num($_POST['precio']);
            $cant   = num($_POST['cantidad']);
            $min    = num($_POST['stock_minimo']);

            if ($nombre === '' || $cat === 0) throw new Exception('Completá el nombre y la categoría.');
            if (!in_array($unidad, ['unidad', 'peso', 'metro'])) throw new Exception('Unidad de venta inválida.');
            if ($costo < 0 || $precio < 0 || $cant < 0 || $min < 0) throw new Exception('No se admiten valores negativos.');

            $pdo->beginTransaction();

            if ($id === 0) {
                // ALTA
                $pdo->prepare("INSERT INTO producto (id_categoria, codigo_barras, nombre, unidad_venta, costo, precio)
                               VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$cat, $codigo, $nombre, $unidad, $costo, $precio]);
                $id = (int)$pdo->lastInsertId();

                $pdo->prepare("INSERT INTO stock (id_producto, id_sucursal, cantidad, stock_minimo) VALUES (?, ?, ?, ?)")
                    ->execute([$id, $suc, $cant, $min]);

                if ($cant > 0) {
                    $pdo->prepare("INSERT INTO movimiento_stock (id_producto, id_sucursal, tipo, cantidad) VALUES (?, ?, 'entrada', ?)")
                        ->execute([$id, $suc, $cant]);
                }
                $msg = 'Producto creado.';

            } else {
                // MODIFICACIÓN
                $st = $pdo->prepare("SELECT costo, precio FROM producto WHERE id_producto = ?");
                $st->execute([$id]);
                $old = $st->fetch();

                // Si cambió el costo o el precio, guardamos los valores anteriores
                if ($old && ((float)$old['costo'] != $costo || (float)$old['precio'] != $precio)) {
                    $pdo->prepare("INSERT INTO historial_precio (id_producto, costo_anterior, precio_anterior) VALUES (?, ?, ?)")
                        ->execute([$id, $old['costo'], $old['precio']]);
                }

                $pdo->prepare("UPDATE producto
                               SET id_categoria = ?, codigo_barras = ?, nombre = ?, unidad_venta = ?, costo = ?, precio = ?
                               WHERE id_producto = ?")
                    ->execute([$cat, $codigo, $nombre, $unidad, $costo, $precio, $id]);

                $st = $pdo->prepare("SELECT cantidad FROM stock WHERE id_producto = ? AND id_sucursal = ?");
                $st->execute([$id, $suc]);
                $cantAnterior = (float)($st->fetchColumn() ?: 0);

                $pdo->prepare("INSERT INTO stock (id_producto, id_sucursal, cantidad, stock_minimo) VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad), stock_minimo = VALUES(stock_minimo)")
                    ->execute([$id, $suc, $cant, $min]);

                // Si se corrigió el stock a mano, queda registrado como ajuste
                $dif = $cant - $cantAnterior;
                if (abs($dif) > 0.0001) {
                    $pdo->prepare("INSERT INTO movimiento_stock (id_producto, id_sucursal, tipo, cantidad) VALUES (?, ?, 'ajuste', ?)")
                        ->execute([$id, $suc, $dif]);
                }
                $msg = 'Producto actualizado.';
            }
            $pdo->commit();

        } elseif ($accion === 'baja') {
            $id = (int)($_POST['id_producto'] ?? 0);
            $pdo->prepare("UPDATE producto SET activo = 0 WHERE id_producto = ?")->execute([$id]);
            $msg = 'Producto dado de baja.';
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($ex instanceof PDOException) {
            $err = ($ex->getCode() == '23000')
                ? 'Ya existe un producto con ese código de barras o la categoría ya existe.'
                : 'Error al guardar en la base de datos.';
        } else {
            $err = $ex->getMessage();
        }
    }
}

// Producto a editar (si viene ?editar=ID)
$edit = null;
if ($puede && isset($_GET['editar'])) {
    $st = $pdo->prepare("SELECT p.*, COALESCE(s.cantidad, 0) AS cantidad, COALESCE(s.stock_minimo, 0) AS stock_minimo
                         FROM producto p
                         LEFT JOIN stock s ON s.id_producto = p.id_producto AND s.id_sucursal = ?
                         WHERE p.id_producto = ?");
    $st->execute([$suc, (int)$_GET['editar']]);
    $edit = $st->fetch() ?: null;
}

$categorias = $pdo->query("SELECT id_categoria, nombre FROM categoria ORDER BY nombre")->fetchAll();

// Listado con búsqueda
$q = trim($_GET['q'] ?? '');
$st = $pdo->prepare("SELECT p.*, c.nombre AS categoria, COALESCE(s.cantidad, 0) AS cantidad, COALESCE(s.stock_minimo, 0) AS stock_minimo
                     FROM producto p
                     INNER JOIN categoria c ON c.id_categoria = p.id_categoria
                     LEFT JOIN stock s ON s.id_producto = p.id_producto AND s.id_sucursal = ?
                     WHERE p.activo = 1 AND (p.nombre LIKE ? OR p.codigo_barras LIKE ?)
                     ORDER BY p.nombre
                     LIMIT 200");
$st->execute([$suc, "%$q%", "%$q%"]);
$productos = $st->fetchAll();

require 'includes/header.php';
?>
<h1>Productos y stock</h1>

<?php if ($msg): ?><div class="ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="error"><?= e($err) ?></div><?php endif; ?>

<?php if ($puede): ?>
<div class="tarjeta">
    <h3>Nueva categoría</h3>
    <form method="post" class="grid">
        <input type="hidden" name="accion" value="categoria">
        <div><label>Nombre</label><input type="text" name="nombre_categoria" required></div>
        <div><button type="submit">Crear categoría</button></div>
    </form>
</div>

<div class="tarjeta">
    <h3><?= $edit ? 'Editar producto' : 'Nuevo producto' ?></h3>
    <form method="post" class="grid">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id_producto" value="<?= e($edit['id_producto'] ?? 0) ?>">

        <div><label>Nombre</label>
            <input type="text" name="nombre" required value="<?= e($edit['nombre'] ?? '') ?>"></div>

        <div><label>Código de barras (opcional)</label>
            <input type="text" name="codigo_barras" value="<?= e($edit['codigo_barras'] ?? '') ?>"></div>

        <div><label>Categoría</label>
            <select name="id_categoria" required>
                <option value="">Elegir...</option>
                <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id_categoria'] ?>" <?= ($edit && $edit['id_categoria'] == $c['id_categoria']) ? 'selected' : '' ?>>
                    <?= e($c['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select></div>

        <div><label>Se vende por</label>
            <select name="unidad_venta">
                <?php foreach (['unidad', 'peso', 'metro'] as $u): ?>
                <option value="<?= $u ?>" <?= ($edit && $edit['unidad_venta'] === $u) ? 'selected' : '' ?>><?= $u ?></option>
                <?php endforeach; ?>
            </select></div>

        <div><label>Costo</label>
            <input type="number" step="0.01" min="0" name="costo" required value="<?= e($edit['costo'] ?? '0') ?>"></div>

        <div><label>Precio de venta</label>
            <input type="number" step="0.01" min="0" name="precio" required value="<?= e($edit['precio'] ?? '0') ?>"></div>

        <div><label>Stock actual</label>
            <input type="number" step="0.001" min="0" name="cantidad" required value="<?= e($edit['cantidad'] ?? '0') ?>"></div>

        <div><label>Stock mínimo</label>
            <input type="number" step="0.001" min="0" name="stock_minimo" required value="<?= e($edit['stock_minimo'] ?? '0') ?>"></div>

        <div>
            <button type="submit"><?= $edit ? 'Guardar cambios' : 'Crear producto' ?></button>
            <?php if ($edit): ?><a class="boton" href="productos.php" style="background:#888">Cancelar</a><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="tarjeta">
    <form method="get" class="grid">
        <div><label>Buscar por nombre o código</label>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Ej: martillo o 7791234567890"></div>
        <div><button type="submit">Buscar</button></div>
    </form>
</div>

<table>
    <tr>
        <th>Producto</th><th>Código</th><th>Categoría</th><th>Unidad</th>
        <th>Costo</th><th>Precio</th><th>Stock</th><th>Mínimo</th>
        <?php if ($puede): ?><th>Acciones</th><?php endif; ?>
    </tr>
    <?php foreach ($productos as $p): ?>
    <tr class="<?= ((float)$p['cantidad'] <= (float)$p['stock_minimo']) ? 'bajo' : '' ?>">
        <td><?= e($p['nombre']) ?></td>
        <td><?= e($p['codigo_barras']) ?></td>
        <td><?= e($p['categoria']) ?></td>
        <td><?= e($p['unidad_venta']) ?></td>
        <td>$ <?= number_format($p['costo'], 2, ',', '.') ?></td>
        <td>$ <?= number_format($p['precio'], 2, ',', '.') ?></td>
        <td><?= e($p['cantidad']) ?></td>
        <td><?= e($p['stock_minimo']) ?></td>
        <?php if ($puede): ?>
        <td>
            <a class="link" href="productos.php?editar=<?= $p['id_producto'] ?>">Editar</a>
            <form method="post" style="display:inline" onsubmit="return confirm('¿Dar de baja este producto?')">
                <input type="hidden" name="accion" value="baja">
                <input type="hidden" name="id_producto" value="<?= $p['id_producto'] ?>">
                <button type="submit" class="peligro" style="padding:3px 8px">Baja</button>
            </form>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (!$productos): ?><tr><td colspan="9">No hay productos para mostrar.</td></tr><?php endif; ?>
</table>
<?php require 'includes/footer.php'; ?>