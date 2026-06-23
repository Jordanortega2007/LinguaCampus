<?php
require_once 'conexion.php';
header('Content-Type: application/json');
try {
    $stmt = $pdo->query("SELECT id_usuario, nombre_usuario, rol FROM usuarios WHERE nombre_usuario='admin'");
    $user = $stmt->fetch();
    if ($user) {
        echo json_encode(['ok' => true, 'usuario' => $user]);
    } else {
        echo json_encode(['error' => 'Usuario admin no encontrado en la BD']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de conexión: ' . (IN_PRODUCTION ? 'Error interno' : $e->getMessage())]);
}
?>