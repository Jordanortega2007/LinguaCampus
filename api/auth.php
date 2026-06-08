<?php
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? '';

if ($accion === 'login') {
    $stmt = $pdo->prepare("SELECT id_usuario, nombre_usuario, rol, estado FROM usuarios WHERE nombre_usuario = ? AND contraseña = SHA2(?, 256)");
    $stmt->execute([$input['usuario'], $input['password']]);
    $user = $stmt->fetch();

    if ($user && $user['estado'] == 1) {
        $_SESSION['usuario'] = $user;
        echo json_encode(['ok' => true, 'usuario' => [
            'id' => $user['id_usuario'],
            'nombre' => $user['nombre_usuario'],
            'rol' => $user['rol']
        ]]);
    } else {
        echo json_encode(['ok' => false, 'mensaje' => 'Credenciales inválidas o cuenta inactiva']);
    }
}
elseif ($accion === 'logout') {
    session_destroy();
    echo json_encode(['ok' => true]);
}