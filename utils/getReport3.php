<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

// === CONFIGURACIÓN ===
$token = "733a7307cd0dd55c139f57fcaa9269d33033EF2588751D51ECB53AA291A5B6501EF5426B"; // <-- Reemplace con su token válido
$reportResourceId = 28675002; // ID del recurso donde está la plantilla
$reportTemplateId = 1;       // ID de la plantilla
$objectId = 29566197;         // ID de la unidad o grupo

// Intervalo de fechas (UNIX timestamps)
$from = strtotime("2025-10-01 00:00:00");
$to   = strtotime("2025-11-01 00:00:00");


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

// Si el reporte no devolvió tablas, lo devolvemos tal cual
if (!isset($execResponse["reportResult"]["tables"]) || empty($execResponse["reportResult"]["tables"])) {
    echo json_encode(["error" => "El reporte no devolvió tablas", "response" => $execResponse]);
    exit;
}

// === 3️⃣ OBTENER FILAS DE LA TABLA ===
$tableIndex = 0;
$totalRows = $execResponse["reportResult"]["tables"][0]["rows"] ?? 0;
if ($totalRows > 0) {
    $rowsResponse = callWialon("report/get_result_rows", [
        "tableIndex" => $tableIndex,
        "indexFrom" => 0,
        "indexTo" => $totalRows - 1
    ], $sid);
} else {
    $rowsResponse = [];
}

// === 4️⃣ CERRAR REPORTE ===
callWialon("report/cleanup_result", [], $sid);

// === 5️⃣ RESPUESTA FINAL JSON ===
echo json_encode([
    "status" => "success",
    "sid" => $sid,
    "rows_count" => $totalRows,
    "rows" => $rowsResponse
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
