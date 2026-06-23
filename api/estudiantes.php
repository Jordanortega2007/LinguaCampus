<?php
require_once 'conexion.php';
header('Content-Type: application/json; charset=utf-8');

requerirRol(['Administrador']);

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

if ($metodo === 'GET' && $accion === 'listar') {
    try {
        $stmt = $pdo->query("SELECT * FROM estudiantes ORDER BY apellidos, nombres");
        respuestaJSON($stmt->fetchAll());
    } catch (PDOException $e) {
        logAcceso($pdo, 'error_bd', 'Error listar estudiantes: ' . $e->getMessage());
        respuestaJSON(['error' => 'Error al consultar estudiantes'], 500);
    }
}
elseif ($metodo === 'POST' && empty($input['id_estudiante'])) {
    requerirCSRF();
    if (empty($input['nombres']) || empty($input['apellidos']) || empty($input['documento'])) {
        respuestaJSON(['error' => 'Nombres, apellidos y documento son obligatorios'], 400);
    }
    if (!empty($input['correo']) && !validarCorreo($input['correo'])) {
        respuestaJSON(['error' => 'Formato de correo inválido'], 400);
    }
    if (!empty($input['telefono']) && !validarTelefono($input['telefono'])) {
        respuestaJSON(['error' => 'Formato de teléfono inválido'], 400);
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO estudiantes (nombres, apellidos, documento, telefono, correo, fecha_nacimiento, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$input['nombres'], $input['apellidos'], $input['documento'], $input['telefono'] ?? null, $input['correo'] ?? null, $input['fecha_nacimiento'] ?? null, $input['estado'] ?? 1]);
        logAcceso($pdo, 'crear_estudiante', "Creado: {$input['nombres']} {$input['apellidos']}");
        respuestaJSON(['ok' => true, 'mensaje' => 'Estudiante creado', 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        logAcceso($pdo, 'error_bd', 'Error crear estudiante: ' . $e->getMessage());
        respuestaJSON(['error' => 'Error al crear estudiante'], 500);
    }
}
elseif ($metodo === 'POST' && !empty($input['id_estudiante'])) {
    requerirCSRF();
    if (!empty($input['correo']) && !validarCorreo($input['correo'])) {
        respuestaJSON(['error' => 'Formato de correo inválido'], 400);
    }
    if (!empty($input['telefono']) && !validarTelefono($input['telefono'])) {
        respuestaJSON(['error' => 'Formato de teléfono inválido'], 400);
    }
    try {
        $stmt = $pdo->prepare("UPDATE estudiantes SET nombres=?, apellidos=?, documento=?, telefono=?, correo=?, fecha_nacimiento=?, estado=? WHERE id_estudiante=?");
        $stmt->execute([$input['nombres'], $input['apellidos'], $input['documento'], $input['telefono'] ?? null, $input['correo'] ?? null, $input['fecha_nacimiento'] ?? null, $input['estado'] ?? 1, $input['id_estudiante']]);
        logAcceso($pdo, 'actualizar_estudiante', "Actualizado ID: {$input['id_estudiante']}");
        respuestaJSON(['ok' => true, 'mensaje' => 'Estudiante actualizado']);
    } catch (PDOException $e) {
        logAcceso($pdo, 'error_bd', 'Error actualizar estudiante: ' . $e->getMessage());
        respuestaJSON(['error' => 'Error al actualizar estudiante'], 500);
    }
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE id_estudiante=? AND estado=1");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            respuestaJSON(['error' => 'No se puede eliminar: el estudiante tiene inscripciones activas.'], 400);
        }
        $stmt = $pdo->prepare("DELETE FROM estudiantes WHERE id_estudiante=?");
        $stmt->execute([$id]);
        logAcceso($pdo, 'eliminar_estudiante', "Eliminado ID: $id");
        respuestaJSON(['ok' => true, 'mensaje' => 'Estudiante eliminado']);
    } catch (PDOException $e) {
        logAcceso($pdo, 'error_bd', 'Error eliminar estudiante: ' . $e->getMessage());
        respuestaJSON(['error' => 'Error al eliminar estudiante'], 500);
    }
}
else {
    respuestaJSON(['error' => 'Método no permitido'], 405);
}