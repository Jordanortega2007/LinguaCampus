<?php
require_once 'conexion.php';
header('Content-Type: application/json');
requerirRol(['Administrador']);

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

function verificarPrerequisito($pdo, $idEstudiante, $idIdioma, $nivelDeseado) {
    $orden = ['A1'=>1, 'A2'=>2, 'B1'=>3, 'B2'=>4, 'C1'=>5, 'C2'=>6];
    $nivelNum = $orden[$nivelDeseado] ?? 0;
    if ($nivelNum <= 1) return true;
    $nivelAnterior = array_search($nivelNum-1, $orden);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificados c JOIN niveles n ON c.id_nivel = n.id_nivel
                           WHERE c.id_estudiante=? AND n.id_idioma=? AND n.nombre_nivel=?");
    $stmt->execute([$idEstudiante, $idIdioma, $nivelAnterior]);
    return $stmt->fetchColumn() > 0;
}

if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $pdo->query("SELECT ins.*, CONCAT(e.nombres,' ',e.apellidos) AS estudiante, g.horario,
                         i.nombre_idioma, n.nombre_nivel
                         FROM inscripciones ins
                         JOIN estudiantes e ON ins.id_estudiante = e.id_estudiante
                         JOIN grupos g ON ins.id_grupo = g.id_grupo
                         JOIN idiomas i ON g.id_idioma = i.id_idioma
                         JOIN niveles n ON g.id_nivel = n.id_nivel
                         ORDER BY ins.fecha_inscripcion DESC");
    respuestaJSON($stmt->fetchAll());
}
elseif ($metodo === 'POST' && empty($input['id_inscripcion'])) {
    requerirCSRF();
    $idEstudiante = $input['id_estudiante'];
    $idGrupo      = $input['id_grupo'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT g.cupo_maximo, g.id_idioma, n.nombre_nivel, g.id_nivel FROM grupos g JOIN niveles n ON g.id_nivel=n.id_nivel WHERE g.id_grupo=? FOR UPDATE");
        $stmt->execute([$idGrupo]);
        $grupo = $stmt->fetch();
        if (!$grupo) { $pdo->rollBack(); respuestaJSON(['error' => 'Grupo no existe'], 400); }

        $cupoOcupado = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE id_grupo=? AND estado=1 FOR UPDATE");
        $cupoOcupado->execute([$idGrupo]);
        if ($cupoOcupado->fetchColumn() >= $grupo['cupo_maximo']) {
            $pdo->rollBack(); respuestaJSON(['error' => 'El grupo ha alcanzado su cupo máximo.'], 400);
        }

        if (!verificarPrerequisito($pdo, $idEstudiante, $grupo['id_idioma'], $grupo['nombre_nivel'])) {
            $pdo->rollBack(); respuestaJSON(['error' => 'El estudiante no ha aprobado el nivel anterior requerido.'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO inscripciones (id_estudiante, id_grupo, fecha_inscripcion, estado) VALUES (?,?,CURDATE(),1)");
        $stmt->execute([$idEstudiante, $idGrupo]);

        $pdo->commit();
        logAcceso($pdo, 'crear_inscripcion', "Estudiante ID $idEstudiante inscrito en grupo ID $idGrupo");
        respuestaJSON(['ok' => true, 'mensaje' => 'Inscripción realizada']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        logAcceso($pdo, 'error_bd', 'Error en inscripción: ' . $e->getMessage());
        respuestaJSON(['error' => 'Error al realizar inscripción'], 500);
    }
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("UPDATE inscripciones SET estado=0 WHERE id_inscripcion=?");
    $stmt->execute([$id]);
    logAcceso($pdo, 'cancelar_inscripcion', "Cancelada inscripción ID: $id");
    respuestaJSON(['ok' => true, 'mensaje' => 'Inscripción cancelada']);
}