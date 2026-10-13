<?php
// Buscador SEGURO: misma funcionalidad que buscador-vulnerable.php
// pero usando PDO::prepare() + bindValue() en vez de concatenar el input
header('Content-Type: application/json; charset=utf-8');

require_once '../config/conexion.php';

// Solo aceptamos GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["estado" => "error", "mensaje" => "Método no permitido"]);
    exit;
}

$q = trim($_GET['q'] ?? '');

// Validaciones basicas antes de tocar la base
if ($q === '') {
    http_response_code(400);
    echo json_encode(["estado" => "error", "mensaje" => "Ingresá un término de búsqueda"], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($q) > 100) { // el campo nombre es VARCHAR(100), no tiene sentido buscar algo mas largo
    http_response_code(400);
    echo json_encode(["estado" => "error", "mensaje" => "La búsqueda es demasiado larga"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Escapamos los comodines del LIKE (% y _) para que el usuario no pueda
// mandar "%" y traerse toda la tabla. Esto no es por inyeccion (eso lo
// resuelve el prepare) sino para que el LIKE busque el texto literal.
$qLike = '%' . addcslashes($q, '%_\\') . '%';

try {
    // El SQL queda fijo, el dato viaja aparte en los placeholders
    $sql = "SELECT id, nombre, categoria, precio, stock
            FROM productos
            WHERE nombre LIKE :nombre OR categoria LIKE :categoria
            ORDER BY nombre ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':nombre', $qLike, PDO::PARAM_STR);
    $stmt->bindValue(':categoria', $qLike, PDO::PARAM_STR);
    $stmt->execute();

    $productos = $stmt->fetchAll();

    http_response_code(200);
    echo json_encode([
        "estado" => "exito",
        "total"  => count($productos),
        "data"   => $productos
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // No le mostramos el error real al cliente (daria info de la BD)
    error_log("Error en buscador-seguro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["estado" => "error", "mensaje" => "Error interno del servidor"]);
}
