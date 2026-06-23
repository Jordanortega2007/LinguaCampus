<?php
require_once 'conexion.php';
header('Content-Type: application/json');
requerirRol(['Administrador']);

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $pdo->query("SELECT * FROM docentes ORDER BY apellidos, nombres");
    respuestaJSON($stmt->fetchAll());
}
elseif ($metodo === 'POST' && empty($input['id_docente'])) {
    requerirCSRF();
    if (empty($input['nombres']) || empty($input['apellidos']) || empty($input['documento']) || empty($input['idioma_principal'])) {
        respuestaJSON(['error' => 'Campos obligatorios: nombres, apellidos, documento, idioma principal'], 400);
    }
    if (!empty($input['correo']) && !validarCorreo($input['correo'])) {
        respuestaJSON(['error' => 'Formato de correo inválido'], 400);
    }
    if (!empty($input['telefono']) && !validarTelefono($input['telefono'])) {
        respuestaJSON(['error' => 'Formato de teléfono inválido'], 400);
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO docentes (nombres, apellidos, documento, idioma_principal, nivel_certificado, telefono, correo, estado) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$input['nombres'], $input['apellidos'], $input['documento'], $input['idioma_principal'], $input['nivel_certificado']??'', $input['telefono']??null, $input['correo']??null, $input['estado']??1]);
        logAcceso($pdo, 'crear_docente', "Creado: {$input['nombres']} {$input['apellidos']}");
        respuestaJSON(['ok' => true, 'mensaje' => 'Docente creado', 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        logAcceso($pdo, 'error_bd', 'Error crear docente: ' . $e->getMessage());
        respuestaJSON(['error' => 'Error al crear docente'], 500);
    }
}
elseif ($metodo === 'POST' && !empty($input['id_docente'])) {
    requerirCSRF();
    if (!empty($input['correo']) && !validarCorreo($input['correo'])) {
        respuestaJSON(['error' => 'Formato de correo inválido'], 400);
    }
    if (!empty($input['telefono']) && !validarTelefono($input['telefono'])) {
        respuestaJSON(['error' => 'Formato de teléfono inválido'], 400);
    }
    try {
        $stmt = $pdo->prepare("UPDATE docentes SET nombres=?, apellidos=?, documento=?, idioma_principal=?, nivel_certificado=?, telefono=?, correo=?, estado=? WHERE id_docente=?");
        $stmt->execute([$input['nombres'], $input['apellidos'], $input['documento'], $input['idioma_principal'], $input['nivel_certificado']??'', $input['telefono']??null, $input['correo']??null, $input['estado']??1, $input['id_docente']]);
        logAcceso($pdo, 'actualizar_docente', "Actualizado ID: {$input['id_docente']}");
        respuestaJSON(['ok' => true, 'mensaje' => 'Docente actualizado']);
    } catch (PDOException $e) {
        logAcceso($pdo, 'error_bd', 'Error actualizar docente: ' . $e->getMessage());
        respuestaJSON(['error' => 'Error al actualizar docente'], 500);
    }
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE id_docente=? AND estado=1");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        respuestaJSON(['error' => 'El docente tiene grupos asignados.'], 400);
    }
    $stmt = $pdo->prepare("DELETE FROM docentes WHERE id_docente=?");
    $stmt->execute([$id]);
    logAcceso($pdo, 'eliminar_docente', "Eliminado ID: $id");
    respuestaJSON(['ok' => true, 'mensaje' => 'Docente eliminado']);
}