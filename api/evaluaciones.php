<?php
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['error'=>'No autorizado']); exit; }

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $pdo->query("SELECT ev.*, CONCAT(e.nombres,' ',e.apellidos) AS estudiante, g.horario
                         FROM evaluaciones ev
                         JOIN inscripciones ins ON ev.id_inscripcion = ins.id_inscripcion
                         JOIN estudiantes e ON ins.id_estudiante = e.id_estudiante
                         JOIN grupos g ON ins.id_grupo = g.id_grupo
                         ORDER BY ev.fecha_evaluacion DESC");
    echo json_encode($stmt->fetchAll());
}
elseif ($metodo === 'POST' && empty($input['id_evaluacion'])) {
    if (empty($input['id_inscripcion']) || empty($input['modulo']) || !isset($input['nota'])) {
        http_response_code(400); echo json_encode(['error' => 'Inscripción, módulo y nota son obligatorios']); exit;
    }
    if ($input['nota'] < 0 || $input['nota'] > 5) {
        http_response_code(400); echo json_encode(['error' => 'La nota debe estar entre 0 y 5']); exit;
    }
    $stmt = $pdo->prepare("INSERT INTO evaluaciones (id_inscripcion, modulo, nota, fecha_evaluacion) VALUES (?,?,?,?)");
    $stmt->execute([$input['id_inscripcion'], $input['modulo'], $input['nota'], $input['fecha_evaluacion'] ?? date('Y-m-d')]);
    echo json_encode(['ok' => true, 'mensaje' => 'Evaluación registrada']);
}
elseif ($metodo === 'POST' && !empty($input['id_evaluacion'])) {
    $stmt = $pdo->prepare("UPDATE evaluaciones SET modulo=?, nota=?, fecha_evaluacion=? WHERE id_evaluacion=?");
    $stmt->execute([$input['modulo'], $input['nota'], $input['fecha_evaluacion'], $input['id_evaluacion']]);
    echo json_encode(['ok' => true, 'mensaje' => 'Evaluación actualizada']);
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM evaluaciones WHERE id_evaluacion=?");
    $stmt->execute([(int)$_GET['id']]);
    echo json_encode(['ok' => true, 'mensaje' => 'Evaluación eliminada']);
}