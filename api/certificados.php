<?php
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['error'=>'No autorizado']); exit; }

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $pdo->query("SELECT c.*, CONCAT(e.nombres,' ',e.apellidos) AS estudiante, n.nombre_nivel, i.nombre_idioma
                         FROM certificados c
                         JOIN estudiantes e ON c.id_estudiante = e.id_estudiante
                         JOIN niveles n ON c.id_nivel = n.id_nivel
                         JOIN idiomas i ON n.id_idioma = i.id_idioma
                         ORDER BY c.fecha_emision DESC");
    echo json_encode($stmt->fetchAll());
}
elseif ($metodo === 'POST' && $accion === 'emitir') {
    $idEstudiante = $input['id_estudiante'];
    $idNivel      = $input['id_nivel'];

    // Obtener información del nivel
    $stmt = $pdo->prepare("SELECT n.nombre_nivel, n.id_idioma, i.nombre_idioma FROM niveles n JOIN idiomas i ON n.id_idioma=i.id_idioma WHERE n.id_nivel=?");
    $stmt->execute([$idNivel]);
    $nivel = $stmt->fetch();
    if (!$nivel) { http_response_code(400); echo json_encode(['error'=>'Nivel no válido']); exit; }

    // 1. Verificar que el estudiante tenga una inscripción activa en un grupo de ese nivel
    $stmt = $pdo->prepare("SELECT ins.id_inscripcion FROM inscripciones ins
                           JOIN grupos g ON ins.id_grupo = g.id_grupo
                           WHERE ins.id_estudiante=? AND g.id_nivel=? AND ins.estado=1");
    $stmt->execute([$idEstudiante, $idNivel]);
    $inscripcion = $stmt->fetch();
    if (!$inscripcion) { http_response_code(400); echo json_encode(['error'=>'El estudiante no tiene inscripción activa en ese nivel.']); exit; }

    // 2. Verificar que tenga evaluaciones en todos los módulos (4 módulos predefinidos)
    $modulos = ['Gramática', 'Conversación', 'Escritura', 'Comprensión auditiva'];
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT modulo) FROM evaluaciones WHERE id_inscripcion=?");
    $stmt->execute([$inscripcion['id_inscripcion']]);
    $modulosRegistrados = $stmt->fetchColumn();
    if ($modulosRegistrados < 4) {
        http_response_code(400); echo json_encode(['error'=>'Faltan evaluaciones. Se requieren los 4 módulos.']); exit;
    }

    // 3. Calcular promedio y verificar >= 3.5
    $stmt = $pdo->prepare("SELECT AVG(nota) FROM evaluaciones WHERE id_inscripcion=?");
    $stmt->execute([$inscripcion['id_inscripcion']]);
    $promedio = round($stmt->fetchColumn(), 2);
    if ($promedio < 3.5) {
        http_response_code(400); echo json_encode(['error'=>"Promedio insuficiente: $promedio. Se requiere mínimo 3.5"]); exit;
    }

    // Emitir certificado
    $stmt = $pdo->prepare("INSERT INTO certificados (id_estudiante, id_nivel, fecha_emision) VALUES (?,?,CURDATE())");
    $stmt->execute([$idEstudiante, $idNivel]);
    echo json_encode(['ok' => true, 'mensaje' => "Certificado emitido. Promedio: $promedio"]);
}