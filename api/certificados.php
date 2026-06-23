<?php
require_once 'conexion.php';
header('Content-Type: application/json');
requerirRol(['Administrador']);

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
    respuestaJSON($stmt->fetchAll());
}
elseif ($metodo === 'POST' && $accion === 'emitir') {
    requerirCSRF();
    $idEstudiante = $input['id_estudiante'];
    $idNivel      = $input['id_nivel'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT n.nombre_nivel, n.id_idioma, i.nombre_idioma FROM niveles n JOIN idiomas i ON n.id_idioma=i.id_idioma WHERE n.id_nivel=? FOR UPDATE");
        $stmt->execute([$idNivel]);
        $nivel = $stmt->fetch();
        if (!$nivel) { $pdo->rollBack(); respuestaJSON(['error'=>'Nivel no válido'], 400); }

        $stmt = $pdo->prepare("SELECT ins.id_inscripcion FROM inscripciones ins
                               JOIN grupos g ON ins.id_grupo = g.id_grupo
                               WHERE ins.id_estudiante=? AND g.id_nivel=? AND ins.estado=1 FOR UPDATE");
        $stmt->execute([$idEstudiante, $idNivel]);
        $inscripcion = $stmt->fetch();
        if (!$inscripcion) { $pdo->rollBack(); respuestaJSON(['error'=>'El estudiante no tiene inscripción activa en ese nivel.'], 400); }

        $modulosEsperados = 4;
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT modulo) FROM evaluaciones WHERE id_inscripcion=?");
        $stmt->execute([$inscripcion['id_inscripcion']]);
        $modulosRegistrados = $stmt->fetchColumn();
        if ($modulosRegistrados < $modulosEsperados) {
            $pdo->rollBack(); respuestaJSON(['error'=>'Faltan evaluaciones. Se requieren los 4 módulos.'], 400);
        }

        $stmt = $pdo->prepare("SELECT AVG(nota) FROM evaluaciones WHERE id_inscripcion=?");
        $stmt->execute([$inscripcion['id_inscripcion']]);
        $promedio = round($stmt->fetchColumn(), 2);
        if ($promedio < 3.5) {
            $pdo->rollBack(); respuestaJSON(['error'=>"Promedio insuficiente: $promedio. Se requiere mínimo 3.5"], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO certificados (id_estudiante, id_nivel, fecha_emision) VALUES (?,?,CURDATE())");
        $stmt->execute([$idEstudiante, $idNivel]);

        $pdo->commit();
        logAcceso($pdo, 'emitir_certificado', "Certificado emitido para estudiante ID $idEstudiante, nivel ID $idNivel, promedio $promedio");
        respuestaJSON(['ok' => true, 'mensaje' => "Certificado emitido. Promedio: $promedio"]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        logAcceso($pdo, 'error_bd', 'Error emitir certificado: ' . $e->getMessage());
        respuestaJSON(['error' => 'Error al emitir certificado'], 500);
    }
}