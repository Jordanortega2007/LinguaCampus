<?php
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['error'=>'No autorizado']); exit; }

$metodo = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? '';

if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $pdo->query("SELECT * FROM docentes ORDER BY apellidos, nombres");
    echo json_encode($stmt->fetchAll());
}
elseif ($metodo === 'POST' && empty($input['id_docente'])) {
    if (empty($input['nombres']) || empty($input['apellidos']) || empty($input['idioma_principal'])) {
        http_response_code(400); echo json_encode(['error' => 'Campos obligatorios: nombres, apellidos, idioma principal']); exit;
    }
    $stmt = $pdo->prepare("INSERT INTO docentes (nombres, apellidos, idioma_principal, nivel_certificado, telefono, correo, estado) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$input['nombres'], $input['apellidos'], $input['idioma_principal'], $input['nivel_certificado']??'', $input['telefono']??null, $input['correo']??null, $input['estado']??1]);
    echo json_encode(['ok' => true, 'mensaje' => 'Docente creado', 'id' => $pdo->lastInsertId()]);
}
elseif ($metodo === 'POST' && !empty($input['id_docente'])) {
    $stmt = $pdo->prepare("UPDATE docentes SET nombres=?, apellidos=?, idioma_principal=?, nivel_certificado=?, telefono=?, correo=?, estado=? WHERE id_docente=?");
    $stmt->execute([$input['nombres'], $input['apellidos'], $input['idioma_principal'], $input['nivel_certificado'], $input['telefono']??null, $input['correo']??null, $input['estado']??1, $input['id_docente']]);
    echo json_encode(['ok' => true, 'mensaje' => 'Docente actualizado']);
}
elseif ($metodo === 'DELETE' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE id_docente=? AND estado=1");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        http_response_code(400); echo json_encode(['error' => 'El docente tiene grupos asignados.']); exit;
    }
    $stmt = $pdo->prepare("DELETE FROM docentes WHERE id_docente=?");
    $stmt->execute([$id]);
    echo json_encode(['ok' => true, 'mensaje' => 'Docente eliminado']);
}