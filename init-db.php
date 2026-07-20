<?php
$dbPath = __DIR__ . '/data/tips.db';
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Crear tabla de usuarios
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        is_admin INTEGER DEFAULT 0,
        activo INTEGER DEFAULT 1
    )");

    // 2. Crear tabla de tips
    $db->exec("CREATE TABLE IF NOT EXISTS tips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marca TEXT,
        modelo TEXT,
        tv_type TEXT,
        sintoma TEXT,
        solucion TEXT,
        fecha_consulta DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Crear admin si no existe (Contraseña: admin2026)
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin2026', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password_hash, is_admin) VALUES ('admin', '$hash', 1)");
        echo "✅ Usuario 'admin' creado con contraseña 'admin2026'<br>";
    } else {
        echo "✅ El usuario 'admin' ya existe.<br>";
    }
    
    echo "✅ Base de datos lista en: $dbPath";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>