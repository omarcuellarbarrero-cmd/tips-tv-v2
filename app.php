<?php
// ============================================================
// app.php - Sistema de Diagnóstico de TVs (MEJORADO)
// Mejoras aplicadas: Sanitización de inputs + Manejo seguro de errores BD
// ============================================================

// 1. Iniciar sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true
    ]);
}

// 2. CANDADO DE SEGURIDAD: Si no hay usuario, o si es admin, fuera de aquí.
if (!isset($_SESSION['user_id']) || !empty($_SESSION['is_admin'])) {
    header('Location: index.php');
    exit;
}

// 3. Obtener datos del usuario (ya sanitizado al mostrar)
$username = htmlspecialchars($_SESSION['username'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');

// ============================================================
// 🔒 CONFIGURACIÓN DE SANITIZACIÓN
// ============================================================

/**
 * Sanitiza una cadena de texto para búsqueda
 * Permite letras, números, espacios, guiones, puntos y paréntesis (comunes en modelos de TV)
 * @param string $input Cadena a sanitizar
 * @param int $maxLength Longitud máxima permitida
 * @return string Cadena sanitizada
 */
function sanitizeSearchString(string $input, int $maxLength = 100): string {
    // Eliminar caracteres peligrosos, mantener solo alfanuméricos, espacios y algunos símbolos comunes en modelos
    $cleaned = preg_replace('/[^a-zA-Z0-9\s\-\.\(\)\/]/u', '', $input);
    // Limitar longitud
    $cleaned = substr($cleaned, 0, $maxLength);
    // Eliminar espacios múltiples
    return trim(preg_replace('/\s+/', ' ', $cleaned));
}

/**
 * Valida que el tipo de TV sea uno de los permitidos
 * @param string $input Valor recibido
 * @return string Valor validado o cadena vacía
 */
function validateTvType(string $input): string {
    $allowed = ['TRC', 'LCD', 'LED', 'OLED', 'QLED', 'Plasma'];
    return in_array(strtoupper($input), $allowed, true) ? strtoupper($input) : '';
}

/**
 * Genera token CSRF si no existe
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica token CSRF
 */
function verifyCsrfToken(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// ============================================================
// 🔒 SANITIZACIÓN DE INPUTS GET
// ============================================================

$filters = [
    'marca' => sanitizeSearchString($_GET['marca'] ?? ''),
    'modelo' => sanitizeSearchString($_GET['modelo'] ?? ''),
    'tv_type' => validateTvType($_GET['tv_type'] ?? ''),
    'sintoma' => sanitizeSearchString($_GET['sintoma'] ?? '', 200)
];

// Verificar CSRF solo si hay filtros activos (protección contra búsquedas maliciosas)
$search_active = false;
$csrf_error = false;

if (!empty($filters['marca']) || !empty($filters['modelo']) || !empty($filters['tv_type']) || !empty($filters['sintoma'])) {
    $search_active = true;

    // Solo verificar CSRF si el método es POST, para GET permitimos búsquedas directas
    // pero podemos agregar rate limiting si es necesario
}

// ============================================================
// 🔒 CONEXIÓN A BASE DE DATOS CON MANEJO SEGURO DE ERRORES
// ============================================================

$dbPath = __DIR__ . '/data/tips.db';
$db = null;
$dbError = null;
$tips = [];

try {
    // Verificar que el archivo de BD existe
    if (!file_exists($dbPath)) {
        throw new Exception('Base de datos no encontrada.');
    }

    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log interno del error real (solo servidor)
    error_log('[DB ERROR] ' . date('Y-m-d H:i:s') . ' | ' . $e->getMessage() . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $dbError = 'Error de conexión con la base de datos. Por favor, intente más tarde o contacte al administrador.';

} catch (Exception $e) {
    error_log('[DB ERROR] ' . date('Y-m-d H:i:s') . ' | ' . $e->getMessage());
    $dbError = 'Error de conexión con la base de datos. Por favor, intente más tarde o contacte al administrador.';
}

// ============================================================
// 🔒 BÚSQUEDA CON PREPARED STATEMENTS (ya existente, reforzada)
// ============================================================

if ($search_active && $db !== null && $dbError === null) {
    try {
        $sql = "SELECT * FROM tips WHERE 1=1";
        $params = [];

        if (!empty($filters['marca'])) {
            $sql .= " AND marca LIKE :marca";
            $params[':marca'] = '%' . $filters['marca'] . '%';
        }
        if (!empty($filters['modelo'])) {
            $sql .= " AND modelo LIKE :modelo";
            $params[':modelo'] = '%' . $filters['modelo'] . '%';
        }
        if (!empty($filters['tv_type'])) {
            $sql .= " AND tv_type = :tv_type";
            $params[':tv_type'] = $filters['tv_type'];
        }
        if (!empty($filters['sintoma'])) {
            $sql .= " AND sintoma LIKE :sintoma";
            $params[':sintoma'] = '%' . $filters['sintoma'] . '%';
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $tips = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log('[SEARCH ERROR] ' . date('Y-m-d H:i:s') . ' | ' . $e->getMessage());
        $dbError = 'Error al realizar la búsqueda. Por favor, intente con otros términos.';
    }
}

// Token CSRF para el formulario
$csrfToken = getCsrfToken();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistente Técnico - Método OC</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .app-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .logo { max-width: 150px; height: auto; }
        .btn-logout {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn-logout:hover { background: #c0392b; }
        .welcome { font-size: 24px; color: #2c3e50; margin-bottom: 10px; }

        /* Mensaje de error de BD */
        .db-error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            text-align: center;
        }
        .db-error-icon { font-size: 32px; margin-bottom: 10px; }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 3px solid #eee;
        }
        .tab-btn {
            padding: 15px 30px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            margin-bottom: -3px;
            transition: all 0.3s;
        }
        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Formulario de búsqueda */
        .search-section, .ai-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            color: white;
        }
        .search-section h2, .ai-section h2 {
            margin-bottom: 20px;
            font-size: 22px;
        }
        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 14px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            background: rgba(255,255,255,0.95);
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.3);
        }
        .btn-search, .btn-ai {
            background: white;
            color: #667eea;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn-search:hover, .btn-ai:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-clear {
            background: transparent;
            color: white;
            padding: 12px 20px;
            border: 2px solid white;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        .btn-clear:hover {
            background: rgba(255,255,255,0.1);
        }

        /* AI Diagnostic */
        .ai-input-area { margin-bottom: 20px; }
        .ai-input-area textarea {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }
        .ai-result {
            background: white;
            color: #2c3e50;
            padding: 25px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 5px solid #667eea;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        .ai-loading {
            text-align: center;
            padding: 40px;
            color: white;
        }
        .spinner {
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Resultados */
        .results-section { margin-top: 30px; }
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .results-header h3 {
            color: #2c3e50;
            font-size: 20px;
        }
        .results-count {
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        /* Tabla de tips */
        .tips-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .tips-table thead {
            background: #2c3e50;
            color: white;
        }
        .tips-table th, .tips-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .tips-table th {
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }
        .tips-table tbody tr:hover {
            background: #f8f9fa;
        }
        .tips-table td {
            font-size: 14px;
            color: #555;
        }
        .tip-marca {
            font-weight: 600;
            color: #2c3e50;
        }
        .tip-modelo {
            color: #666;
            font-size: 13px;
        }
        .tip-type {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .type-TRC { background: #ffeaa7; color: #d63031; }
        .type-LCD { background: #74b9ff; color: #0984e3; }
        .type-LED { background: #55efc4; color: #00b894; }
        .type-OLED { background: #a29bfe; color: #6c5ce7; }
        .type-QLED { background: #fd79a8; color: #e84393; }
        .type-Plasma { background: #fab1a0; color: #d63031; }
        .tip-sintoma {
            color: #e17055;
            font-weight: 500;
        }
        .tip-solucion {
            color: #00b894;
            line-height: 1.5;
        }

        /* Mensajes */
        .no-results {
            background: #fff3cd;
            color: #856404;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #ffeeba;
        }
        .welcome-message {
            background: #d4edda;
            color: #155724;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #c3e6cb;
        }

        @media (max-width: 768px) {
            .app-container { padding: 20px; }
            .header { flex-direction: column; gap: 15px; }
            .search-form { grid-template-columns: 1fr; }
            .tips-table { font-size: 12px; }
            .tips-table th, .tips-table td { padding: 10px 5px; }
            .tabs { flex-direction: column; }
            .tab-btn { border-bottom: none; border-left: 3px solid transparent; }
            .tab-btn.active { border-left-color: #667eea; border-bottom: none; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="header">
            <img src="logo-nuevo-metodo-oc.webp" alt="Método OC" class="logo" onerror="this.style.display='none'">
            <div>
                <span style="font-size: 18px; margin-right: 15px; color: #2c3e50;"> Hola, <?= $username ?></span>
                <a href="logout.php" class="btn-logout">🚪 Salir</a>
            </div>
        </div>

        <h1 class="welcome">📺 Sistema de Diagnóstico de TVs</h1>

        <?php if ($dbError): ?>
        <div class="db-error">
            <div class="db-error-icon">⚠️</div>
            <strong>Error del sistema</strong>
            <p style="margin-top: 8px;"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('ai')">🤖 Asistente IA</button>
            <button class="tab-btn" onclick="switchTab('search')">🔍 Base de Datos</button>
        </div>

        <!-- SECCIÓN IA -->
        <div id="ai-tab" class="tab-content active">
            <div class="ai-section">
                <h2>🤖 Diagnóstico con Inteligencia Artificial</h2>
                <p style="margin-bottom: 20px; opacity: 0.9;">Describe los síntomas del televisor y la IA te dará un diagnóstico técnico preliminar.</p>

                <div class="ai-input-area">
                    <div class="search-form" style="margin-bottom: 15px;">
                        <div class="form-group">
                            <label>Marca:</label>
                            <input type="text" id="ai-brand" placeholder="Ej: Samsung" maxlength="50">
                        </div>
                        <div class="form-group">
                            <label>Modelo:</label>
                            <input type="text" id="ai-model" placeholder="Ej: UN55TU7000" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label>Tipo:</label>
                            <select id="ai-type">
                                <option value="">No especificado</option>
                                <option value="TRC">TRC (CRT)</option>
                                <option value="LCD">LCD</option>
                                <option value="LED">LED</option>
                                <option value="OLED">OLED</option>
                                <option value="QLED">QLED</option>
                                <option value="Plasma">Plasma</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Describe los síntomas detalladamente:</label>
                        <textarea id="ai-symptoms" placeholder="Ej: El televisor enciende pero no hay imagen, solo se escucha el audio. El LED de standby parpadea 3 veces..." maxlength="1000"></textarea>
                    </div>
                </div>

                <button class="btn-ai" onclick="getAIDiagnostic()">🚀 Obtener Diagnóstico IA</button>
            </div>

            <div id="ai-result-container"></div>
        </div>

        <!-- SECCIÓN BÚSQUEDA -->
        <div id="search-tab" class="tab-content">
            <div class="search-section">
                <h2>🔍 Buscar Tips de Diagnóstico</h2>
                <form method="GET" action="app.php" class="search-form">
                    <!-- Token CSRF oculto -->
                    <input type="hidden" name="csrf" value="<?= $csrfToken ?>">

                    <div class="form-group">
                        <label>Marca:</label>
                        <input type="text" name="marca" value="<?= htmlspecialchars($filters['marca'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: Samsung, LG..." maxlength="100">
                    </div>
                    <div class="form-group">
                        <label>Modelo:</label>
                        <input type="text" name="modelo" value="<?= htmlspecialchars($filters['modelo'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: UN55TU7000..." maxlength="100">
                    </div>
                    <div class="form-group">
                        <label>Tipo de TV:</label>
                        <select name="tv_type">
                            <option value="">Todos los tipos</option>
                            <option value="TRC" <?= $filters['tv_type'] === 'TRC' ? 'selected' : '' ?>>TRC (CRT)</option>
                            <option value="LCD" <?= $filters['tv_type'] === 'LCD' ? 'selected' : '' ?>>LCD</option>
                            <option value="LED" <?= $filters['tv_type'] === 'LED' ? 'selected' : '' ?>>LED</option>
                            <option value="OLED" <?= $filters['tv_type'] === 'OLED' ? 'selected' : '' ?>>OLED</option>
                            <option value="QLED" <?= $filters['tv_type'] === 'QLED' ? 'selected' : '' ?>>QLED</option>
                            <option value="Plasma" <?= $filters['tv_type'] === 'Plasma' ? 'selected' : '' ?>>Plasma</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Síntoma:</label>
                        <input type="text" name="sintoma" value="<?= htmlspecialchars($filters['sintoma'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: No enciende, sin imagen..." maxlength="200">
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn-search">🔍 Buscar</button>
                        <?php if ($search_active): ?>
                            <a href="app.php" class="btn-clear">✖ Limpiar filtros</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- SECCIÓN DE RESULTADOS -->
            <div class="results-section">
                <?php if ($search_active && !$dbError): ?>
                    <div class="results-header">
                        <h3>📋 Resultados de la búsqueda</h3>
                        <span class="results-count"><?= count($tips) ?> tip(s) encontrado(s)</span>
                    </div>

                    <?php if (count($tips) > 0): ?>
                        <table class="tips-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Marca / Modelo</th>
                                    <th>Tipo</th>
                                    <th>Síntoma</th>
                                    <th>Solución / Diagnóstico</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tips as $tip): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($tip['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <div class="tip-marca"><?= htmlspecialchars($tip['marca'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="tip-modelo"><?= htmlspecialchars($tip['modelo'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td>
                                        <span class="tip-type type-<?= htmlspecialchars($tip['tv_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tip['tv_type'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="tip-sintoma"><?= htmlspecialchars($tip['sintoma'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="tip-solucion"><?= htmlspecialchars($tip['solucion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-results">
                            <strong>😕 No se encontraron tips</strong> con esos filtros. Intenta con otros criterios de búsqueda o usa el Asistente IA.
                        </div>
                    <?php endif; ?>

                <?php elseif (!$search_active && !$dbError): ?>
                    <div class="welcome-message">
                        <h3>👋 ¡Bienvenido al sistema!</h3>
                        <p style="margin-top: 10px;">Usa los filtros de arriba para buscar tips de diagnóstico por marca, modelo, tipo de TV o síntoma.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

   <script>
    // Cambiar entre tabs
    function switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        event.target.classList.add('active');
        document.getElementById(tabName + '-tab').classList.add('active');
    }

    // Sanitizar inputs del formulario IA antes de enviar
    function sanitizeInput(str) {
        return str.replace(/[^a-zA-Z0-9\s\-\.\(\)\/]/gu, '').trim().substring(0, 1000);
    }

    // Obtener diagnóstico híbrido (BD + IA)
    async function getAIDiagnostic() {
        const symptoms = sanitizeInput(document.getElementById('ai-symptoms').value.trim());
        const brand = sanitizeInput(document.getElementById('ai-brand').value.trim());
        const model = sanitizeInput(document.getElementById('ai-model').value.trim());
        const type = document.getElementById('ai-type').value;

        if (!symptoms) {
            alert('Por favor describe los síntomas del televisor');
            return;
        }

        const container = document.getElementById('ai-result-container');
        container.innerHTML = `
            <div class="ai-section">
                <div class="ai-loading">
                    <div class="spinner"></div>
                    <p>Buscando en base de datos y consultando IA...</p>
                </div>
            </div>
        `;

        try {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000); // 15 seg timeout

            const response = await fetch('ai-diagnostic.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ symptoms, brand, model, type }),
                signal: controller.signal
            });

            clearTimeout(timeout);

            const data = await response.json();

            if (response.ok && data.success) {
                let html = '<div class="ai-section"><h2>📊 Resultados del Análisis</h2>';

                // 1. Mostrar Tips Locales (Prioridad)
                if (data.local_tips && data.local_tips.length > 0) {
                    html += `<div style="background: #d4edda; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #28a745;">
                        <h3 style="color: #155724; margin-bottom: 10px;">✅ Encontrado en Base de Datos (${data.local_tips.length} tips)</h3>`;

                    data.local_tips.forEach(tip => {
                        html += `<div style="background: white; padding: 10px; margin-bottom: 10px; border-radius: 5px; border: 1px solid #c3e6cb;">
                            <strong style="color: #2c3e50;">${escapeHtml(tip.marca)} ${escapeHtml(tip.modelo)} (${escapeHtml(tip.tv_type)}):</strong>
                            <p style="margin: 5px 0; color: #e17055;"><em>Síntoma:</em> ${escapeHtml(tip.sintoma)}</p>
                            <p style="margin: 0; color: #00b894;"><em>Solución:</em> ${escapeHtml(tip.solucion)}</p>
                        </div>`;
                    });
                    html += `</div>`;
                }

                // 2. Mostrar Diagnóstico IA (Si se generó)
                if (data.ai_diagnostic) {
                    let badge = data.ai_saved ? '<span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px;">💾 Guardado en BD para revisión</span>' : '';

                    html += `<div style="background: #e7f3ff; padding: 15px; border-radius: 8px; border-left: 5px solid #007bff;">
                        <h3 style="color: #004085; margin-bottom: 10px;">🤖 Diagnóstico de Inteligencia Artificial ${badge}</h3>
                        <div class="ai-result" style="border-left: none; background: transparent; padding: 0;">${escapeHtml(data.ai_diagnostic).replace(/\n/g, '<br>')}</div>
                    </div>`;
                }

                // 3. Mensaje si no hay nada
                if ((!data.local_tips || data.local_tips.length === 0) && !data.ai_diagnostic) {
                    html += `<div class="no-results">No se encontraron resultados en la base de datos y la IA no pudo generar un diagnóstico.</div>`;
                }

                html += '</div>';
                container.innerHTML = html;

            } else {
                container.innerHTML = `<div class="ai-section" style="background: #f8d7da; color: #721c24;"><h2>❌ Error</h2><p>${escapeHtml(data.error || 'Error desconocido')}</p></div>`;
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                container.innerHTML = `<div class="ai-section" style="background: #fff3cd; color: #856404;"><h2>⏱️ Tiempo de espera agotado</h2><p>La consulta tomó demasiado tiempo. Intente nuevamente.</p></div>`;
            } else {
                container.innerHTML = `<div class="ai-section" style="background: #f8d7da; color: #721c24;"><h2>❌ Error de Conexión</h2><p>No se pudo conectar con el servidor. Verifique su conexión a internet.</p></div>`;
            }
        }
    }

    // Función para escapar HTML y prevenir XSS en resultados dinámicos
    function escapeHtml(text) {
        if (typeof text !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>
</body>
</html>