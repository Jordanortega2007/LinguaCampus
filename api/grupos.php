<?php
require_once 'conexion.php';
header('Content-Type: application/json');
requerirRol(['Administrador']);

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $pdo->query("SELECT g.*, i.nombre_idioma, n.nombre_nivel, CONCAT(d.nombres,' ',d.apellidos) AS docente
                         FROM grupos g
                         JOIN idiomas i ON g.id_idioma = i.id_idioma
                         JOIN niveles n ON g.id_nivel = n.id_nivel
                         JOIN docentes d ON g.id_docente = d.id_docente
                         ORDER BY i.nombre_idioma, n.nombre_nivel");
    respuestaJSON($stmt->fetchAll());
}
elseif ($metodo === 'POST' && empty($input['id_grupo'])) {
    requerirCSRF();
    if (empty($input['id_idioma']) || empty($input['id_nivel']) || empty($input['id_docente']) || empty($input['horario'])) {
        respuestaJSON(['error' => 'Todos los campos son obligatorios'], 400);
    }
    $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE id_docente=? AND horario=? AND estado=1");
    $check->execute([$input['id_docente'], $input['horario']]);
    if ($check->fetchColumn() > 0) {
        respuestaJSON(['error' => 'El docente ya tiene un grupo en ese horario.'], 400);
    }
    $stmt = $pdo->prepare("INSERT INTO grupos (id_idioma, id_nivel, id_docente, horario, cupo_maximo, estado) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$input['id_idioma'], $input['id_nivel'], $input['id_docente'], $input['horario'], $input['cupo_maximo']??15, $input['estado']??1]);
    logAcceso($pdo, 'crear_grupo', "Grupo creado: {$input['horario']}");
    respuestaJSON(['ok' => true, 'mensaje' => 'Grupo creado']);
}
elseif ($metodo === 'POST' && !empty($input['id_grupo'])) {
    requerirCSRF();
    $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE id_docente=? AND horario=? AND estado=1 AND id_grupo != ?");
    $check->execute([$input['id_docente'], $input['horario'], $input['id_grupo']]);
    if ($check->fetchColumn() > 0) {
        respuestaJSON(['error' => 'El docente ya tiene un grupo en ese horario.'], 400);
    }
    $stmt = $pdo->prepare("UPDATE grupos SET id_idioma=?, id_nivel=?, id_docente=?, horario=?, cupo_maximo=?, estado=? WHERE id_grupo=?");
    $stmt->execute([$input['id_idioma'], $input['id_nivel'], $input['id_docente'], $input['horario'], $input['cupo_maximo'], $input['estado'], $input['id_grupo']]);
    logAcceso($pdo, 'actualizar_grupo', "Actualizado grupo ID {$input['id_grupo']}");
    respuestaJSON(['ok' => true, 'mensaje' => 'Grupo actualizado']);
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE id_grupo=? AND estado=1");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        respuestaJSON(['error' => 'El grupo tiene inscripciones activas.'], 400);
    }
    $stmt = $pdo->prepare("DELETE FROM grupos WHERE id_grupo=?");
    $stmt->execute([$id]);
    logAcceso($pdo, 'eliminar_grupo', "Eliminado grupo ID: $id");
    respuestaJSON(['ok' => true, 'mensaje' => 'Grupo eliminado']);
}