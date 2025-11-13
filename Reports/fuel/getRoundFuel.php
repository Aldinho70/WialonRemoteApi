<?php
// Permitir solicitudes desde cualquier origen
header("Access-Control-Allow-Origin: *");
// Permitir métodos GET y POST
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// Permitir headers personalizados si los necesitas
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

$fromParam = $_GET['from'] ?? null;
$toParam   = $_GET['to'] ?? null;
$idunit  = $_GET['idUnit'] ?? null; 

// === CONFIGURACIÓN ===
$token = "733a7307cd0dd55c139f57fcaa9269d33033EF2588751D51ECB53AA291A5B6501EF5426B"; // <-- Reemplace con su token válido
$reportResourceId = 28675002; // ID del recurso donde está la plantilla
$reportTemplateId = 19;       // ID de la plantilla
$objectId = $idunit;         // ID de la unidad o grupo

// Intervalo de fechas (UNIX timestamps)
// $from = strtotime("2025-10-01");
// $to   = strtotime("2025-11-01");
$from = strtotime($fromParam);
$to   = strtotime($toParam);

// === FUNCIÓN GENÉRICA DE LLAMADA A WIALON ===
function callWialon($svc, $params, $sid = null) {
    $url = "https://hst-api.wialon.com/wialon/ajax.html?svc=" . $svc;
    if ($sid) $url .= "&sid=" . $sid;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ["params" => json_encode($params)]);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// === 1️⃣ LOGIN ===
$loginResponse = callWialon("token/login", ["token" => $token]);
if (!isset($loginResponse["eid"])) {
    echo json_encode(["error" => "No se pudo iniciar sesión", "response" => $loginResponse]);
    exit;
}
$sid = $loginResponse["eid"];

// === 2️⃣ EJECUTAR REPORTE ===
$execParams = [
    "reportResourceId" => $reportResourceId,
    "reportTemplateId" => $reportTemplateId,
    "reportObjectId" => $objectId,
    "reportObjectSecId" => 0,
    "interval" => [
        "from" => $from,
        "to" => $to,
        "flags" => 0
    ]
];
$execResponse = callWialon("report/exec_report", $execParams, $sid);

// Verificar si el reporte devolvió tablas
if (!isset($execResponse["reportResult"]["tables"]) || empty($execResponse["reportResult"]["tables"])) {
    echo json_encode(["error" => "El reporte no devolvió tablas", "response" => $execResponse]);
    exit;
}

// === 3️⃣ OBTENER Y PROCESAR FILAS DE LA TABLA ===
$tableIndex = 0;
$totalRows = $execResponse["reportResult"]["tables"][0]["rows"] ?? 0;
$processedRows = [];

if ($totalRows > 0) {
    $rowsResponse = callWialon("report/get_result_rows", [
        "tableIndex" => $tableIndex,
        "indexFrom" => 0,
        "indexTo" => $totalRows - 1
    ], $sid);

    // Procesar filas para obtener solo la info deseada
    foreach ($rowsResponse as $row) {
        $c = $row['c'];

        $processedRows[] = [
            "unidad" => $c[1] ?? null, // nombre de la unidad
            "rendimiento" => $c[4] ?? null, // objeto con dirección y coordenadas
        ];
    }
} else {
    $processedRows = [];
}

// === 4️⃣ CERRAR REPORTE ===
callWialon("report/cleanup_result", [], $sid);

// === 5️⃣ RESPUESTA FINAL JSON ===
echo json_encode([
    "status" => "success",
    "sid" => $sid,
    "rows_count" => $totalRows,
    "rows" => $processedRows
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
