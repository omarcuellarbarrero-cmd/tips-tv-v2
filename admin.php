<?php
session_start();

// 🔒 SEGURIDAD: Si no es admin, fuera.
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}

$dbPath = __DIR__ . '/data/tips.db';
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de BD: " . $e->getMessage());
}

$message = '';
$edit_tip = null;
$edit_user = null;

// ==========================================
// VERIFICAR SI ESTAMOS EDITANDO ALGO (GET)
// ==========================================
if (isset($_GET['edit_tip'])) {
    $stmt = $db->prepare("SELECT * FROM tips WHERE id = ?");
    $stmt->execute([$_GET['edit_tip']]);
    $edit_tip = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (isset($_GET['edit_user'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit_user']]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ==========================================
// PROCESAR ACCIONES (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- TIPS ---
    if ($action === 'add_tip') {
        $stmt = $db->prepare("INSERT INTO tips (marca, modelo, tv_type, sintoma, solucion) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['marca'], $_POST['modelo'], $_POST['tv_type'], $_POST['sintoma'], $_POST['solucion']]);
        $message = "✅ Tip agregado correctamente.";
    } 
    elseif ($action === 'update_tip') {
        $stmt = $db->prepare("UPDATE tips SET marca=?, modelo=?, tv_type=?, sintoma=?, solucion=? WHERE id=?");
        $stmt->execute([$_POST['marca'], $_POST['modelo'], $_POST['tv_type'], $_POST['sintoma'], $_POST['solucion'], $_POST['id']]);
        $message = "✅ Tip actualizado correctamente.";
    }
    elseif ($action === 'delete_tip') {
        $stmt = $db->prepare("DELETE FROM tips WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = "🗑️ Tip eliminado.";
    }

    // --- USUARIOS ---
    elseif ($action === 'add_user') {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        try {
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, is_admin) VALUES (?, ?, ?)");
            $stmt->execute([strtolower($_POST['username']), $hash, isset($_POST['is_admin']) ? 1 : 0]);
            $message = "✅ Usuario creado correctamente.";
        } catch (PDOException $e) {
            $message = "❌ Error: El usuario ya existe.";
        }
    }
    elseif ($action === 'update_user') {
        if (empty($_POST['password'])) {
            // Si no hay contraseña, no la actualizamos
            $stmt = $db->prepare("UPDATE users SET username=?, is_admin=? WHERE id=?");
            $stmt->execute([strtolower($_POST['username']), isset($_POST['is_admin']) ? 1 : 0, $_POST['id']]);
        } else {
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET username=?, password_hash=?, is_admin=? WHERE id=?");
            $stmt->execute([strtolower($_POST['username']), $hash, isset($_POST['is_admin']) ? 1 : 0, $_POST['id']]);
        }
        $message = "✅ Usuario actualizado correctamente.";
    }
    elseif ($action === 'delete_user') {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
        $stmt->execute([$_POST['id']]);
        $message = "🗑️ Usuario eliminado.";
    }

    // Redirigir para limpiar la URL y evitar reenvío de formulario
    header('Location: admin.php?msg=' . urlencode($message));
    exit;
}

// Mostrar mensaje si viene de redirección
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// ==========================================
// OBTENER DATOS
// ==========================================
$tips = $db->query("SELECT * FROM tips ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$users = $db->query("SELECT * FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f9; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; color: white; text-decoration: none; font-size: 14px; display: inline-block; }
        .btn-primary { background: #3498db; }
        .btn-warning { background: #f39c12; }
        .btn-danger { background: #e74c3c; }
        .btn-success { background: #27ae60; }
        .message { padding: 10px; background: #d4edda; color: #155724; border-radius: 4px; margin-bottom: 15px; border: 1px solid #c3e6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: middle; }
        th { background: #f8f9fa; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .tabs { margin-bottom: 20px; }
        .tab-btn { padding: 10px 20px; background: #eee; border: none; cursor: pointer; font-weight: bold; }
        .tab-btn.active { background: #3498db; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .edit-mode { background: #fff3cd; padding: 15px; border: 1px solid #ffeeba; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🛠️ Panel de Administración</h2>
            <div>
                <span>Hola, <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="logout.php" class="btn btn-danger" style="margin-left: 10px;">Cerrar Sesión</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('tips')"> Gestionar Tips</button>
            <button class="tab-btn" onclick="showTab('users')">👥 Gestionar Usuarios</button>
        </div>

        <!-- SECCIÓN TIPS -->
        <div id="tips" class="tab-content active">
            <h3><?= $edit_tip ? '✏️ Editando Tip #' . $edit_tip['id'] : '➕ Agregar Nuevo Tip' ?></h3>
            
            <?php if ($edit_tip): ?>
                <div class="edit-mode">Editando tip. <a href="admin.php">Cancelar edición</a></div>
            <?php endif; ?>

            <form method="POST" style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <input type="hidden" name="action" value="<?= $edit_tip ? 'update_tip' : 'add_tip' ?>">
                <?php if ($edit_tip): ?>
                    <input type="hidden" name="id" value="<?= $edit_tip['id'] ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group"><label>Marca</label><input type="text" name="marca" value="<?= $edit_tip ? htmlspecialchars($edit_tip['marca']) : '' ?>" required></div>
                    <div class="form-group"><label>Modelo</label><input type="text" name="modelo" value="<?= $edit_tip ? htmlspecialchars($edit_tip['modelo']) : '' ?>" required></div>
                    <div class="form-group"><label>Tipo</label>
                        <select name="tv_type">
                            <option value="TRC" <?= ($edit_tip && $edit_tip['tv_type'] == 'TRC') ? 'selected' : '' ?>>TRC</option>
                            <option value="LCD" <?= ($edit_tip && $edit_tip['tv_type'] == 'LCD') ? 'selected' : '' ?>>LCD</option>
                            <option value="LED" <?= ($edit_tip && $edit_tip['tv_type'] == 'LED') ? 'selected' : '' ?>>LED</option>
                            <option value="OLED" <?= ($edit_tip && $edit_tip['tv_type'] == 'OLED') ? 'selected' : '' ?>>OLED</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Síntoma</label><input type="text" name="sintoma" value="<?= $edit_tip ? htmlspecialchars($edit_tip['sintoma']) : '' ?>" required></div>
                </div>
                <div class="form-group"><label>Solución / Diagnóstico</label><textarea name="solucion" rows="3" required><?= $edit_tip ? htmlspecialchars($edit_tip['solucion']) : '' ?></textarea></div>
                <button type="submit" class="btn <?= $edit_tip ? 'btn-warning' : 'btn-success' ?>"><?= $edit_tip ? '💾 Actualizar Tip' : '💾 Guardar Tip' ?></button>
                <?php if ($edit_tip): ?> <a href="admin.php" class="btn btn-primary">Cancelar</a> <?php endif; ?>
            </form>

            <h3>Lista de Tips (<?= count($tips) ?>)</h3>
            <table>
                <thead><tr><th>ID</th><th>Marca/Modelo</th><th>Síntoma</th><th>Solución</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach ($tips as $tip): ?>
                    <tr>
                        <td><?= $tip['id'] ?></td>
                        <td><strong><?= htmlspecialchars($tip['marca']) ?></strong><br><small><?= htmlspecialchars($tip['modelo']) ?> (<?= $tip['tv_type'] ?>)</small></td>
                        <td><?= htmlspecialchars($tip['sintoma']) ?></td>
                        <td><small><?= htmlspecialchars(substr($tip['solucion'], 0, 80)) ?>...</small></td>
                        <td>
                            <a href="?edit_tip=<?= $tip['id'] ?>" class="btn btn-warning" style="padding: 5px 10px;">️</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este tip?');">
                                <input type="hidden" name="action" value="delete_tip">
                                <input type="hidden" name="id" value="<?= $tip['id'] ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 5px 10px;">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- SECCIÓN USUARIOS -->
        <div id="users" class="tab-content">
            <h3><?= $edit_user ? '✏️ Editando Usuario: ' . htmlspecialchars($edit_user['username']) : '➕ Agregar Nuevo Usuario' ?></h3>
            
            <?php if ($edit_user): ?>
                <div class="edit-mode">Editando usuario. <a href="admin.php">Cancelar edición</a></div>
            <?php endif; ?>

            <form method="POST" style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-bottom: 20px; max-width: 400px;">
                <input type="hidden" name="action" value="<?= $edit_user ? 'update_user' : 'add_user' ?>">
                <?php if ($edit_user): ?>
                    <input type="hidden" name="id" value="<?= $edit_user['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group"><label>Usuario</label><input type="text" name="username" value="<?= $edit_user ? htmlspecialchars($edit_user['username']) : '' ?>" required></div>
                <div class="form-group"><label>Contraseña <?= $edit_user ? '(Dejar vacío para no cambiar)' : '' ?></label><input type="password" name="password" <?= $edit_user ? '' : 'required' ?>></div>
                <div class="form-group">
                    <label><input type="checkbox" name="is_admin" <?= ($edit_user && $edit_user['is_admin']) ? 'checked' : '' ?>> Es Administrador</label>
                </div>
                <button type="submit" class="btn <?= $edit_user ? 'btn-warning' : 'btn-success' ?>"><?= $edit_user ? '💾 Actualizar Usuario' : '💾 Crear Usuario' ?></button>
                <?php if ($edit_user): ?> <a href="admin.php" class="btn btn-primary">Cancelar</a> <?php endif; ?>
            </form>

            <h3>Lista de Usuarios</h3>
            <table>
                <thead><tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                        <td><?= $user['is_admin'] ? '👑 Admin' : '🔧 Técnico' ?></td>
                        <td>
                            <a href="?edit_user=<?= $user['id'] ?>" class="btn btn-warning" style="padding: 5px 10px;">✏️</a>
                            <?php if (!$user['is_admin']): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este usuario?');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 5px 10px;">🗑️</button>
                            </form>
                            <?php else: ?>
                                <small style="color:gray;">Protegido</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>