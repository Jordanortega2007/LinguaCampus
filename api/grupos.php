<?php
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['error'=>'No autorizado']); exit; }

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
    echo json_encode($stmt->fetchAll());
}
elseif ($metodo === 'POST' && empty($input['id_grupo'])) {
    if (empty($input['id_idioma']) || empty($input['id_nivel']) || empty($input['id_docente']) || empty($input['horario'])) {
        http_response_code(400); echo json_encode(['error' => 'Todos los campos son obligatorios']); exit;
    }
    // Regla: docente no puede tener dos grupos en el mismo horario
    $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE id_docente=? AND horario=? AND estado=1");
    $check->execute([$input['id_docente'], $input['horario']]);
    if ($check->fetchColumn() > 0) {
        http_response_code(400); echo json_encode(['error' => 'El docente ya tiene un grupo en ese horario.']); exit;
    }
    $stmt = $pdo->prepare("INSERT INTO grupos (id_idioma, id_nivel, id_docente, horario, cupo_maximo, estado) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$input['id_idioma'], $input['id_nivel'], $input['id_docente'], $input['horario'], $input['cupo_maximo']??15, $input['estado']??1]);
    echo json_encode(['ok' => true, 'mensaje' => 'Grupo creado']);
}
elseif ($metodo === 'POST' && !empty($input['id_grupo'])) {
    // Validar cruce de horario excluyendo el mismo grupo
    $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE id_docente=? AND horario=? AND estado=1 AND id_grupo != ?");
    $check->execute([$input['id_docente'], $input['horario'], $input['id_grupo']]);
    if ($check->fetchColumn() > 0) {
        http_response_code(400); echo json_encode(['error' => 'El docente ya tiene un grupo en ese horario.']); exit;
    }
    $stmt = $pdo->prepare("UPDATE grupos SET id_idioma=?, id_nivel=?, id_docente=?, horario=?, cupo_maximo=?, estado=? WHERE id_grupo=?");
    $stmt->execute([$input['id_idioma'], $input['id_nivel'], $input['id_docente'], $input['horario'], $input['cupo_maximo'], $input['estado'], $input['id_grupo']]);
    echo json_encode(['ok' => true, 'mensaje' => 'Grupo actualizado']);
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE id_grupo=? AND estado=1");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        http_response_code(400); echo json_encode(['error' => 'El grupo tiene inscripciones activas.']); exit;
    }
    $stmt = $pdo->prepare("DELETE FROM grupos WHERE id_grupo=?");
    $stmt->execute([$id]);
    echo json_encode(['ok' => true, 'mensaje' => 'Grupo eliminado']);
}