<?php
session_start();
// AGREGA ESTA LÍNEA:
$username = $_SESSION['username'] ?? 'Usuario';
// Si no hay sesi├│n iniciada, enviar al login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
        <!-- Favicon (versión cuadrada si la creaste) -->
    <link rel="icon" type="image/png" href="assets/favicon.webp">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistente Técnico - Diagnóstico</title>
    <link rel="icon" type="image/png" href="Logo-nuevo-metodo-oc.webp">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app-container">

        <header class="app-header">
    <div class="header-brand">
        <img src="Logo-nuevo-metodo-oc.webp" alt="Logo" class="logo-header">
        <h1>Asistente Técnico</h1>
    </div>
            <span id="welcomeUser" class="welcome-user"></span>
            <button id="logoutBtn" class="btn-logout">Salir</button>
        </header>

        <main class="main-content">

            <!-- Seleccionar tipo de TV -->
            <section class="card">
                <h2>Paso 1: ¿Qué tipo de TV es?</h2>
                <div class="tv-type-buttons">
                    <button class="btn-tv-type" data-type="TRC">
                        <span class="icon">📺</span>
                        <span class="label">TV TRC</span>
                        <span class="desc">Televisor de tubo (gordo)</span>
                    </button>
                    <button class="btn-tv-type" data-type="LCD/LED">
                        <span class="icon">🖥️</span>
                        <span class="label">TV LCD / LED</span>
                        <span class="desc">Televisor plano</span>
                    </button>
                </div>
                <p id="tvTypeSelected" class="selection-info"></p>
            </section>

            <!-- Formulario de datos -->
            <section class="card">
                <h2>Paso 2: Datos del televisor</h2>
                <form id="diagnosticForm">
                    <div class="form-group">
                        <label for="brand">🏷️ Marca:</label>
                        <input type="text" id="brand" name="brand" placeholder="Ej: Samsung, LG, Sony, RCA..." required>
                    </div>

                    <div class="form-group">
                        <label for="model">🔢 Modelo:</label>
                        <input type="text" id="model" name="model" placeholder="Ej: UN55TU7000, 21T3000..." required>
                    </div>

                    <div class="form-group">
                        <label for="symptom">⚠️ Síntoma (¿qué le pasa?):</label>
                        <textarea id="symptom" name="symptom" rows="4" placeholder="Ej: No enciende, tiene líneas en la pantalla, se apaga solo, no tiene imagen pero sí sonido..." required></textarea>
                    </div>

                    <button type="submit" class="btn-primary btn-search">🔍 Buscar Tip de Diagnóstico</button>
                </form>
            </section>

            <!-- Resultados -->
            <section class="card" id="resultSection" style="display: none;">
                <h2>📋 Resultado del Diagnóstico</h2>
                
                <!-- 🔊 Botón de voz -->
                <button id="btnVoice" class="btn-voice-simple" title="Escuchar en voz alta">
                    <span class="voice-icon-simple">🔊</span>
                    <span id="voiceLabel">Escuchar diagnóstico</span>
                </button>
                
                <div class="result-content" id="resultContent"></div>
                <button id="newSearchBtn" class="btn-secondary">🔄 Nueva Búsqueda</button>
            </section>
        </main>
        <footer class="app-footer">
            <p>Asistente Técnico v2.0 — Powered by Groq AI</p>
        </footer>

    </div>

    <!-- 🔐 API Key se inyecta desde Coolify en producción -->
<script>
    if (!window.GROQ_API_KEY) {
        window.GROQ_API_KEY = '';
    }
</script>
    <script src="app.js"></script>
</body>
</html>
