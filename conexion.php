<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli('localhost', 'root', '123456', 'agenda');
    $mysqli->set_charset('utf8mb4'); 
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die('No se pudo conectar a la base de datos: ' . $e->getMessage());
}

// ==========================================
// 1. Procesamiento y validación de filtros
// ==========================================
$q = mb_substr(trim($_GET['q'] ?? ''), 0, 60);        // Texto, máximo 60 caracteres
$categoriaId = (int) ($_GET['categoria'] ?? 0);        // 0 = todas

// Obtener categorías válidas desde la base de datos para la validación cruzada
$categorias = $mysqli->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$idsValidos = array_map('intval', array_column($categorias, 'id'));

// Validación estricta: si mandan un ID de categoría inventado o manipulado, lo devolvemos a 0 (todas)
if (!in_array($categoriaId, $idsValidos, true)) {
    $categoriaId = 0;                                  
}

// Bandera para saber si el usuario está aplicando algún filtro activo
$buscando = ($q !== '' || $categoriaId !== 0);

// ==========================================
// 2. Consulta segura con Prepared Statements
// ==========================================
$sql = "SELECT e.*, c.nombre AS categoria_nombre 
        FROM eventos e 
        INNER JOIN categorias c ON e.categoria_id = c.id 
        WHERE 1=1";

$params = [];
$types = "";

if ($q !== '') {
    $sql .= " AND e.titulo LIKE ?";
    $params[] = "%" . $q . "%";
    $types .= "s";
}

if ($categoriaId !== 0) {
    $sql .= " AND e.categoria_id = ?";
    $params[] = $categoriaId;
    $types .= "i";
}

$sql .= " ORDER BY e.fecha DESC";

$stmt = $mysqli->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultado = $stmt->get_result();
$eventos = $resultado->fetch_all(MYSQLI_ASSOC);
?>