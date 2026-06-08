<?php
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['error'=>'No autorizado']); exit; }

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $pdo->query("SELECT * FROM idiomas ORDER BY nombre_idioma");
    echo json_encode($stmt->fetchAll());
}
elseif ($metodo === 'POST' && empty($input['id_idioma'])) {
    if (empty($input['nombre_idioma'])) { http_response_code(400); echo json_encode(['error' => 'Nombre del idioma es obligatorio']); exit; }
    $stmt = $pdo->prepare("INSERT INTO idiomas (nombre_idioma, descripcion, estado) VALUES (?,?,?)");
    $stmt->execute([$input['nombre_idioma'], $input['descripcion']??'', $input['estado']??1]);
    echo json_encode(['ok' => true, 'mensaje' => 'Idioma creado']);
}
elseif ($metodo === 'POST' && !empty($input['id_idioma'])) {
    $stmt = $pdo->prepare("UPDATE idiomas SET nombre_idioma=?, descripcion=?, estado=? WHERE id_idioma=?");
    $stmt->execute([$input['nombre_idioma'], $input['descripcion']??'', $input['estado']??1, $input['id_idioma']]);
    echo json_encode(['ok' => true, 'mensaje' => 'Idioma actualizado']);
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // Regla: no eliminar idiomas con grupos asociados
    $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE id_idioma=?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        http_response_code(400); echo json_encode(['error' => 'No se puede eliminar el idioma porque tiene grupos asociados.']); exit;
    }
    // También verificar niveles asociados (aunque con RESTRICT lo impide)
    $stmt = $pdo->prepare("DELETE FROM idiomas WHERE id_idioma=?");
    $stmt->execute([$id]);
    echo json_encode(['ok' => true, 'mensaje' => 'Idioma eliminado']);
}