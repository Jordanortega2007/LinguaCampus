<?php
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['error'=>'No autorizado']); exit; }

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $pdo->query("SELECT n.*, i.nombre_idioma FROM niveles n JOIN idiomas i ON n.id_idioma = i.id_idioma ORDER BY i.nombre_idioma, FIELD(n.nombre_nivel, 'A1','A2','B1','B2','C1','C2')");
    echo json_encode($stmt->fetchAll());
}
elseif ($metodo === 'POST' && empty($input['id_nivel'])) {
    if (empty($input['id_idioma']) || empty($input['nombre_nivel'])) {
        http_response_code(400); echo json_encode(['error' => 'Idioma y nombre del nivel son obligatorios']); exit;
    }
    $stmt = $pdo->prepare("INSERT INTO niveles (id_idioma, nombre_nivel) VALUES (?,?)");
    $stmt->execute([$input['id_idioma'], strtoupper($input['nombre_nivel'])]);
    echo json_encode(['ok' => true, 'mensaje' => 'Nivel creado']);
}
elseif ($metodo === 'POST' && !empty($input['id_nivel'])) {
    $stmt = $pdo->prepare("UPDATE niveles SET id_idioma=?, nombre_nivel=? WHERE id_nivel=?");
    $stmt->execute([$input['id_idioma'], strtoupper($input['nombre_nivel']), $input['id_nivel']]);
    echo json_encode(['ok' => true, 'mensaje' => 'Nivel actualizado']);
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE id_nivel=?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        http_response_code(400); echo json_encode(['error' => 'El nivel está asignado a grupos.']); exit;
    }
    $stmt = $pdo->prepare("DELETE FROM niveles WHERE id_nivel=?");
    $stmt->execute([$id]);
    echo json_encode(['ok' => true, 'mensaje' => 'Nivel eliminado']);
}