<?php
require_once 'conexion.php';
header('Content-Type: application/json');
requerirRol(['Administrador']);

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

if ($metodo === 'GET' && $accion === 'listar') {
    $id_grupo = $_GET['id_grupo'] ?? 0;
    $fecha    = $_GET['fecha'] ?? date('Y-m-d');
    $stmt = $pdo->prepare("SELECT a.id_asistencia, a.presente, ins.id_inscripcion, ins.id_estudiante,
                           CONCAT(e.nombres,' ',e.apellidos) AS estudiante
                           FROM inscripciones ins
                           LEFT JOIN asistencias a ON a.id_inscripcion = ins.id_inscripcion AND a.fecha = ?
                           JOIN estudiantes e ON ins.id_estudiante = e.id_estudiante
                           WHERE ins.id_grupo = ? AND ins.estado = 1
                           ORDER BY e.apellidos, e.nombres");
    $stmt->execute([$fecha, $id_grupo]);
    respuestaJSON($stmt->fetchAll());
} elseif ($metodo === 'POST') {
    requerirCSRF();
    $id_inscripcion = $input['id_inscripcion'];
    $fecha          = $input['fecha'];
    $presente       = $input['presente'] ? 1 : 0;
    $stmt = $pdo->prepare("INSERT INTO asistencias (id_inscripcion, fecha, presente) VALUES (?,?,?)
                           ON DUPLICATE KEY UPDATE presente = ?");
    $stmt->execute([$id_inscripcion, $fecha, $presente, $presente]);
    logAcceso($pdo, 'asistencia', "Asistencia inscripción ID $id_inscripcion fecha $fecha: " . ($presente ? 'presente' : 'ausente'));
    respuestaJSON(['ok' => true, 'mensaje' => 'Asistencia actualizada']);
} else {
    respuestaJSON(['error' => 'Método no permitido'], 405);
}