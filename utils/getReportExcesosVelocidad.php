<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ⚙️ CONFIGURACIÓN INICIAL
$token = "733a7307cd0dd55c139f57fcaa9269d33033EF2588751D51ECB53AA291A5B6501EF5426B";
$baseUrl = "https://hst-api.wialon.com/wialon/ajax.html";

// Función auxiliar para hacer peticiones a la API
function wialonRequest($svc, $params = [], $sid = null) {
    global $baseUrl;
    $query = [
        "svc" => $svc,
        "params" => json_encode($params, JSON_UNESCAPED_UNICODE)
    ];
    if ($sid) $query["sid"] = $sid;

    $url = $baseUrl . "?" . http_build_query($query);

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true);
}

// 1️⃣ LOGIN CON TOKEN
$loginResponse = wialonRequest("token/login", ["token" => $token]);
if (!isset($loginResponse["eid"])) {
    die("❌ Error en login: " . json_encode($loginResponse));
}
$sid = $loginResponse["eid"];
echo "✅ Login exitoso. SID: $sid\n";

// 2️⃣ EJECUTAR REPORTE
$execParams = [
    "reportResourceId" => 28675002, // ID del recurso
    "reportTemplateId" => 19,       // ID del template
    "reportObjectId" => 29714525,   // ID de la unidad
    "reportObjectSecId" => 0,
    "interval" => [
        "from" => 1731196800,
        "to" => 1731283200,
        "flags" => 0
    ]
];

$execResponse = wialonRequest("report/exec_report", $execParams, $sid);
if (!isset($execResponse["reportResult"]["tables"])) {
    die("❌ Error al ejecutar reporte: " . json_encode($execResponse));
}

var_dump($execResponse);

$tables = $execResponse["reportResult"]["tables"];
$totalRows = $tables[0]["rows"];
echo "📊 Filas encontradas: $totalRows\n";

// 3️⃣ OBTENER FILAS DE RESULTADO
$rowParams = [
    "tableIndex" => 0,
    "indexFrom" => 0,
    "indexTo" => $totalRows - 1 // <= índice final
];

$rowsResponse = wialonRequest("report/get_result_rows", $rowParams, $sid);
echo "✅ Filas obtenidas:\n";
print_r($rowsResponse);

// 4️⃣ (OPCIONAL) LIMPIAR RESULTADO
$cleanupResponse = wialonRequest("report/cleanup_result", [], $sid);
echo "🧹 Reporte limpiado correctamente.\n";
?>
