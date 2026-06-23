<?php
require_once 'conexion.php';
header('Content-Type: application/json');
requerirRol(['Administrador']);

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $pdo->query("SELECT n.*, i.nombre_idioma FROM niveles n JOIN idiomas i ON n.id_idioma = i.id_idioma ORDER BY i.nombre_idioma, FIELD(n.nombre_nivel, 'A1','A2','B1','B2','C1','C2')");
    respuestaJSON($stmt->fetchAll());
}
elseif ($metodo === 'POST' && empty($input['id_nivel'])) {
    requerirCSRF();
    if (empty($input['id_idioma']) || empty($input['nombre_nivel'])) {
        respuestaJSON(['error' => 'Idioma y nombre del nivel son obligatorios'], 400);
    }
    $stmt = $pdo->prepare("INSERT INTO niveles (id_idioma, nombre_nivel) VALUES (?,?)");
    $stmt->execute([$input['id_idioma'], strtoupper($input['nombre_nivel'])]);
    logAcceso($pdo, 'crear_nivel', "Nivel {$input['nombre_nivel']} creado para idioma ID {$input['id_idioma']}");
    respuestaJSON(['ok' => true, 'mensaje' => 'Nivel creado']);
}
elseif ($metodo === 'POST' && !empty($input['id_nivel'])) {
    requerirCSRF();
    $stmt = $pdo->prepare("UPDATE niveles SET id_idioma=?, nombre_nivel=? WHERE id_nivel=?");
    $stmt->execute([$input['id_idioma'], strtoupper($input['nombre_nivel']), $input['id_nivel']]);
    logAcceso($pdo, 'actualizar_nivel', "Actualizado nivel ID {$input['id_nivel']}");
    respuestaJSON(['ok' => true, 'mensaje' => 'Nivel actualizado']);
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE id_nivel=?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        respuestaJSON(['error' => 'El nivel está asignado a grupos.'], 400);
    }
    $stmt = $pdo->prepare("DELETE FROM niveles WHERE id_nivel=?");
    $stmt->execute([$id]);
    logAcceso($pdo, 'eliminar_nivel', "Eliminado nivel ID: $id");
    respuestaJSON(['ok' => true, 'mensaje' => 'Nivel eliminado']);
}