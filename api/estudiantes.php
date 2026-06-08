<?php
// ============================================================
// LinguaCampus - API de Estudiantes
// Maneja GET (listar), POST (crear/actualizar), DELETE
// ============================================================

session_start();
require_once 'conexion.php';           // Incluye la conexión PDO

// Cabecera para permitir solicitudes desde el frontend (CORS)
header('Content-Type: application/json; charset=utf-8');

// --- Control de acceso simple (debes verificar sesión en producción) ---
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

// ------------------------------------------------------------
// LISTAR todos los estudiantes (GET ?accion=listar)
// ------------------------------------------------------------
if ($metodo === 'GET' && $accion === 'listar') {
    try {
        $stmt = $pdo->query("SELECT * FROM estudiantes ORDER BY apellidos, nombres");
        $estudiantes = $stmt->fetchAll();
        echo json_encode($estudiantes);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al consultar: ' . $e->getMessage()]);
    }
}
// ------------------------------------------------------------
// CREAR un nuevo estudiante (POST con datos en el body)
// ------------------------------------------------------------
elseif ($metodo === 'POST' && empty($input['id_estudiante'])) {
    // Validaciones básicas (se repiten en frontend)
    if (empty($input['nombres']) || empty($input['apellidos']) || empty($input['documento'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nombres, apellidos y documento son obligatorios']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO estudiantes (nombres, apellidos, documento, telefono, correo, fecha_nacimiento, estado)
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['nombres'],
            $input['apellidos'],
            $input['documento'],
            $input['telefono'] ?? null,
            $input['correo'] ?? null,
            $input['fecha_nacimiento'] ?? null,
            $input['estado'] ?? 1
        ]);
        echo json_encode(['ok' => true, 'mensaje' => 'Estudiante creado', 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear: ' . $e->getMessage()]);
    }
}
// ------------------------------------------------------------
// ACTUALIZAR (POST con id_estudiante)
// ------------------------------------------------------------
elseif ($metodo === 'POST' && !empty($input['id_estudiante'])) {
    try {
        $stmt = $pdo->prepare("UPDATE estudiantes SET nombres=?, apellidos=?, documento=?, telefono=?, correo=?, fecha_nacimiento=?, estado=?
                               WHERE id_estudiante=?");
        $stmt->execute([
            $input['nombres'],
            $input['apellidos'],
            $input['documento'],
            $input['telefono'] ?? null,
            $input['correo'] ?? null,
            $input['fecha_nacimiento'] ?? null,
            $input['estado'] ?? 1,
            $input['id_estudiante']
        ]);
        echo json_encode(['ok' => true, 'mensaje' => 'Estudiante actualizado']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al actualizar: ' . $e->getMessage()]);
    }
}
// ------------------------------------------------------------
// ELIMINAR (DELETE ?id=...)
// ------------------------------------------------------------
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        // Verificar si el estudiante tiene inscripciones activas (regla de negocio)
        $check = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE id_estudiante=? AND estado=1");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'No se puede eliminar: el estudiante tiene inscripciones activas.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM estudiantes WHERE id_estudiante=?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true, 'mensaje' => 'Estudiante eliminado']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar: ' . $e->getMessage()]);
    }
}
else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}