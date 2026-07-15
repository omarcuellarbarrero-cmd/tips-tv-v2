<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ============================================
// 1. RECIBIR DATOS DEL FRONTEND
// ============================================
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Datos no válidos']);
    exit;
}

$marca = strtolower(trim($input['marca'] ?? ''));
$modelo = strtolower(trim($input['modelo'] ?? ''));
$sintoma = strtolower(trim($input['sintoma'] ?? ''));
$tvType = strtolower(trim($input['tvType'] ?? ''));

if (empty($marca) || empty($modelo) || empty($sintoma)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Faltan datos']);
    exit;
}

// ============================================
// 2. CONECTAR A SQLITE
// ============================================
$dbPath = __DIR__ . '/data/tips.db';
$dbDir = dirname($dbPath);

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0775, true);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear tabla si no existe
    $db->exec("CREATE TABLE IF NOT EXISTS tips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marca TEXT NOT NULL,
        modelo TEXT NOT NULL,
        tv_type TEXT NOT NULL,
        sintoma TEXT NOT NULL,
        solucion TEXT NOT NULL,
        fecha_consulta DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Crear índice para búsquedas rápidas
    $db->exec("CREATE INDEX IF NOT EXISTS idx_busqueda ON tips(marca, modelo, tv_type)");
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Error BD: ' . $e->getMessage()]);
    exit;
}

// ============================================
// 3. BUSCAR EN BASE DE DATOS LOCAL
// ============================================
$stmt = $db->prepare("SELECT solucion FROM tips WHERE marca = :marca AND modelo = :modelo AND tv_type = :tv_type AND sintoma LIKE :sintoma LIMIT 1");
$stmt->execute([
    ':marca' => $marca,
    ':modelo' => $modelo,
    ':tv_type' => $tvType,
    ':sintoma' => '%' . $sintoma . '%'
]);
$localResult = $stmt->fetch(PDO::FETCH_ASSOC);

if ($localResult) {
    // ✅ ÉXITO LOCAL: Respuesta instantánea
    echo json_encode([
        'status' => 'success',
        'source' => 'local',
        'data' => $localResult['solucion']
    ]);
    exit;
}

// ============================================
// 4. FALLBACK A GROQ (RED / IA)
// ============================================
$apiKey = getenv('GROQ_API_KEY');
if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'API Key no configurada']);
    exit;
}

$systemPrompt = 'Eres un técnico experto con más de 20 años de experiencia en reparación de televisores TRC (tubo de rayos catódicos) y LCD/LED. Tu función es diagnosticar fallas basándote en la MARCA, MODELO y SÍNTOMA que te proporciona el usuario.

REGLAS ESTRICTAS DE RESPUESTA:
1. Responde SOLO en español, de forma directa y sin saludos ni frases de relleno.
2. NO incluyas enlaces, imágenes, emojis excesivos ni formato Markdown pesado (negritas, cursivas, títulos con #).
3. Usa únicamente viñetas simples con guion (-) y saltos de línea.
4. Sé conciso: máximo 8 líneas en total.
5. Prioriza soluciones prácticas, probadas y de bajo costo.
6. Si no conoces el modelo exacto, da soluciones generales aplicables a esa marca y tipo de TV.

ESTRUCTURA OBLIGATORIA DE LA RESPUESTA (respeta este orden exacto):

CAUSAS PROBABLES:
- [Causa 1 más frecuente]
- [Causa 2]
- [Causa 3 si aplica]

PASOS DE DIAGNÓSTICO:
- [Paso 1 simple y seguro]
- [Paso 2]
- [Paso 3]

SOLUCIÓN:
- [Acción concreta a realizar]
- [Componente a revisar o reemplazar si aplica]

ADVERTENCIA DE SEGURIDAD:
- [Advertencia breve sobre alto voltaje, descarga de capacitores en TRC, o riesgo eléctrico]';

$payload = [
    'model' => 'llama-3.3-70b-versatile',
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => "Tipo de TV: $tvType\nMarca: $marca\nModelo: $modelo\nSíntoma: $sintoma"]
    ],
    'temperature' => 0.3,
    'max_tokens' => 400
];

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Error de Groq: ' . $response]);
    exit;
}

$data = json_decode($response, true);
$aiResponse = $data['choices'][0]['message']['content'] ?? 'No se pudo obtener respuesta';

// ============================================
// 5. GUARDAR EN BASE DE DATOS (Auto-aprendizaje)
// ============================================
$insertStmt = $db->prepare("INSERT INTO tips (marca, modelo, tv_type, sintoma, solucion) VALUES (:marca, :modelo, :tv_type, :sintoma, :solucion)");
$insertStmt->execute([
    ':marca' => $marca,
    ':modelo' => $modelo,
    ':tv_type' => $tvType,
    ':sintoma' => $sintoma,
    ':solucion' => $aiResponse
]);

// ============================================
// 6. DEVOLVER RESPUESTA
// ============================================
echo json_encode([
    'status' => 'success',
    'source' => 'groq',
    'data' => $aiResponse
]);
?>