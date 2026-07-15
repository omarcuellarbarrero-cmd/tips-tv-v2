<?php
session_start();
$error = '';

// Si ya está logueado, ir a la app
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username']));
    $password = $_POST['password'];

    $dbPath = __DIR__ . '/data/tips.db';
    try {
        $db = new PDO('sqlite:' . $dbPath);
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND activo = 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } catch (PDOException $e) {
        $error = 'Error de base de datos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistente Técnico - Ingreso</title>
    <link rel="icon" type="image/png" href="Logo-nuevo-metodo-oc.webp">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo-container">
    <img src="Logo-nuevo-metodo-oc.webp" alt="Logo Asistente Técnico" class="logo-login">
</div>
            <h1>Asistente Técnico</h1>
            <p class="subtitle">Diagnóstico de Televisores con IA</p>

            <form id="loginForm">
                <div class="form-group">
                    <label for="username">👤 Usuario:</label>
                    <input type="text" id="username" name="username" placeholder="Escribe tu usuario" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">🔒 Contraseña:</label>
                    <input type="password" id="password" name="password" placeholder="Escribe tu contraseña" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn-primary">Ingresar</button>

                <div id="errorMessage" class="error-message"></div>
            </form>

                   <footer class="app-footer">
            <p>Asistente Técnico v2.0 — Powered by Groq AI</p>
        </footer>

        </div>
    </div>

    <script src="login.js?v=8"></script>
</body>
</html>
