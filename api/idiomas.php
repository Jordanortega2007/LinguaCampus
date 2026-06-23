<?php
require_once 'conexion.php';
header('Content-Type: application/json');
requerirRol(['Administrador']);

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $pdo->query("SELECT * FROM idiomas ORDER BY nombre_idioma");
    respuestaJSON($stmt->fetchAll());
}
elseif ($metodo === 'POST' && empty($input['id_idioma'])) {
    requerirCSRF();
    if (empty($input['nombre_idioma'])) { respuestaJSON(['error' => 'Nombre del idioma es obligatorio'], 400); }
    $stmt = $pdo->prepare("INSERT INTO idiomas (nombre_idioma, descripcion, estado) VALUES (?,?,?)");
    $stmt->execute([$input['nombre_idioma'], $input['descripcion']??'', $input['estado']??1]);
    logAcceso($pdo, 'crear_idioma', "Creado: {$input['nombre_idioma']}");
    respuestaJSON(['ok' => true, 'mensaje' => 'Idioma creado']);
}
elseif ($metodo === 'POST' && !empty($input['id_idioma'])) {
    requerirCSRF();
    $stmt = $pdo->prepare("UPDATE idiomas SET nombre_idioma=?, descripcion=?, estado=? WHERE id_idioma=?");
    $stmt->execute([$input['nombre_idioma'], $input['descripcion']??'', $input['estado']??1, $input['id_idioma']]);
    logAcceso($pdo, 'actualizar_idioma', "Actualizado ID: {$input['id_idioma']}");
    respuestaJSON(['ok' => true, 'mensaje' => 'Idioma actualizado']);
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE id_idioma=?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        respuestaJSON(['error' => 'No se puede eliminar el idioma porque tiene grupos asociados.'], 400);
    }
    $stmt = $pdo->prepare("DELETE FROM idiomas WHERE id_idioma=?");
    $stmt->execute([$id]);
    logAcceso($pdo, 'eliminar_idioma', "Eliminado ID: $id");
    respuestaJSON(['ok' => true, 'mensaje' => 'Idioma eliminado']);
}