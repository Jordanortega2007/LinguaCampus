<?php
require_once 'conexion.php';
header('Content-Type: application/json');
requerirRol(['Administrador']);

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
    respuestaJSON($stmt->fetchAll());
}
elseif ($metodo === 'POST' && empty($input['id_evaluacion'])) {
    requerirCSRF();
    if (empty($input['id_inscripcion']) || empty($input['modulo']) || !isset($input['nota'])) {
        respuestaJSON(['error' => 'Inscripción, módulo y nota son obligatorios'], 400);
    }
    $nota = floatval($input['nota']);
    if ($nota < 0 || $nota > 5) {
        respuestaJSON(['error' => 'La nota debe estar entre 0 y 5'], 400);
    }
    $stmt = $pdo->prepare("INSERT INTO evaluaciones (id_inscripcion, modulo, nota, fecha_evaluacion) VALUES (?,?,?,?)");
    $stmt->execute([$input['id_inscripcion'], $input['modulo'], $nota, $input['fecha_evaluacion'] ?? date('Y-m-d')]);
    logAcceso($pdo, 'crear_evaluacion', "Evaluación {$input['modulo']} nota $nota para inscripción ID {$input['id_inscripcion']}");
    respuestaJSON(['ok' => true, 'mensaje' => 'Evaluación registrada']);
}
elseif ($metodo === 'POST' && !empty($input['id_evaluacion'])) {
    requerirCSRF();
    $nota = floatval($input['nota']);
    if ($nota < 0 || $nota > 5) {
        respuestaJSON(['error' => 'La nota debe estar entre 0 y 5'], 400);
    }
    $stmt = $pdo->prepare("UPDATE evaluaciones SET modulo=?, nota=?, fecha_evaluacion=? WHERE id_evaluacion=?");
    $stmt->execute([$input['modulo'], $nota, $input['fecha_evaluacion'], $input['id_evaluacion']]);
    logAcceso($pdo, 'actualizar_evaluacion', "Actualizada evaluación ID {$input['id_evaluacion']}");
    respuestaJSON(['ok' => true, 'mensaje' => 'Evaluación actualizada']);
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM evaluaciones WHERE id_evaluacion=?");
    $stmt->execute([(int)$_GET['id']]);
    logAcceso($pdo, 'eliminar_evaluacion', "Eliminada evaluación ID: {$_GET['id']}");
    respuestaJSON(['ok' => true, 'mensaje' => 'Evaluación eliminada']);
}