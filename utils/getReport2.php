<?php
// --- CONFIGURACIÓN BÁSICA ---
$baseUrl = "https://hst-api.wialon.com/wialon/ajax.html";

// Credenciales y datos
$token = "733a7307cd0dd55c139f57fcaa9269d33033EF2588751D51ECB53AA291A5B6501EF5426B"; // ⚠️ Reemplace por su token Wialon
$reportResourceId = 28675002; // ID del recurso donde está el reporte
$reportTemplateId = 1; // ID de la plantilla de reporte
$objectId = 29566197;         // ID de la unidad o grupo
$from = strtotime("-30 day"); // Fecha de inicio
$to = time();                // Fecha fin

// --- FUNCIÓN AUXILIAR ---
function callWialon($svc, $params = [], $sid = null) {
    global $baseUrl;

    $url = $baseUrl . "?svc=" . $svc . "&params=" . urlencode(json_encode($params));
    if ($sid) $url .= "&sid=" . $sid;

    $response = file_get_contents($url);
    return json_decode($response, true);
}

// --- 1️⃣ LOGIN ---
$loginParams = ["token" => $token];
$loginResponse = callWialon("token/login", $loginParams);
if (!isset($loginResponse["eid"])) {
    die("❌ Error al iniciar sesión en Wialon.");
}
$sid = $loginResponse["eid"];
echo "✅ Login exitoso. SID: {$sid}<br>";

// --- 2️⃣ EJECUTAR REPORTE ---
$execParams = [
    "reportResourceId" => $reportResourceId,   // ID del recurso
    "reportTemplateId" => $reportTemplateId,   // ID de la plantilla del reporte
    "reportObjectId" => $objectId,             // ID de la unidad o grupo
    "reportObjectSecId" => 0,                  // Siempre 0 salvo casos con subobjetos
    "interval" => [
        "from" => $from,
        "to" => $to,
        "flags" => 0                           // Obligatorio
    ]
];

$execResponse = callWialon("report/exec_report", $execParams, $sid);


echo "<pre>";
// var_dump($execResponse);
echo "</pre>";

// --- 3️⃣ VALIDAR RESULTADOS ---
if (!isset($execResponse["reportResult"]["tables"]) || empty($execResponse["reportResult"]["tables"])) {
    echo "⚠️ El reporte no devolvió tablas. Verifique fechas, plantilla o unidad.<br>";
    callWialon("report/cleanup_result", [], $sid);
    exit;
}

// --- 4️⃣ OBTENER TABLA PRINCIPAL ---
$reportTables = $execResponse["reportResult"]["tables"];
$tableIndex = $reportTables[0]["index"] ?? 0;

// --- 5️⃣ OBTENER FILAS DE RESULTADO ---
$params = [
    "tableIndex" => $tableIndex,
    "indexFrom" => 0,
    "indexTo" => 50 // Puede ajustar según la cantidad de filas esperada
];
$rowsResponse = callWialon("report/get_result_rows", $params, $sid);

// --- 6️⃣ MOSTRAR RESULTADOS ---
echo "📊 Filas obtenidas:<br>";
echo "<pre>";
print_r($rowsResponse);
echo "</pre>";

// --- 7️⃣ LIMPIAR RESULTADOS ---
callWialon("report/cleanup_result", [], $sid);
echo "🧹 Reporte limpiado correctamente.<br>";

?>
