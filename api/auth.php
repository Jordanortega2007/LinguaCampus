<?php
require_once 'conexion.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? '';

function verificarRateLimit($pdo, $usuario, $ip) {
    $ventana = 300;
    $maxIntentos = 5;
    $limite = date('Y-m-d H:i:s', time() - $ventana);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM log_accesos 
                           WHERE (detalle LIKE ? OR direccion_ip = ?) 
                           AND accion = 'login_fallido' 
                           AND created_at > ?");
    $stmt->execute(["%$usuario%", $ip, $limite]);
    $intentos = (int)$stmt->fetchColumn();
    if ($intentos >= $maxIntentos) {
        respuestaJSON(['ok' => false, 'mensaje' => "Demasiados intentos fallidos. Espere unos segundos e intente nuevamente."], 429);
    }
}

function registrarIntentoLogin($pdo, $usuario, $exitoso) {
    try {
        $stmt = $pdo->prepare("INSERT INTO log_accesos (id_usuario, accion, detalle, direccion_ip) VALUES (NULL, ?, ?, ?)");
        $accion = $exitoso ? 'login_exitoso' : 'login_fallido';
        $detalle = $exitoso ? "Login exitoso para: $usuario" : "Intento fallido para: $usuario";
        $stmt->execute([$accion, $detalle, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (PDOException $e) {}
}

if ($accion === 'login') {
    $usuario = trim($input['usuario'] ?? '');
    $password = $input['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    verificarRateLimit($pdo, $usuario, $ip);

    $stmt = $pdo->prepare("SELECT id_usuario, nombre_usuario, contraseña, rol, estado FROM usuarios WHERE nombre_usuario = ?");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();

    if (!$user) {
        registrarIntentoLogin($pdo, $usuario, false);
        respuestaJSON(['ok' => false, 'mensaje' => 'El usuario no existe'], 401);
    }
    if ($user['estado'] != 1) {
        registrarIntentoLogin($pdo, $usuario, false);
        respuestaJSON(['ok' => false, 'mensaje' => 'Cuenta inactiva'], 401);
    }

    if (password_verify($password, $user['contraseña'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id_usuario' => $user['id_usuario'],
            'nombre_usuario' => $user['nombre_usuario'],
            'rol' => $user['rol']
        ];
        $_SESSION['created'] = time();
        generarCSRFToken();
        registrarIntentoLogin($pdo, $usuario, true);
        respuestaJSON(['ok' => true, 'csrf_token' => $_SESSION['csrf_token'], 'usuario' => [
            'id' => $user['id_usuario'],
            'nombre' => $user['nombre_usuario'],
            'rol' => $user['rol']
        ]]);
    } else {
        registrarIntentoLogin($pdo, $usuario, false);
        respuestaJSON(['ok' => false, 'mensaje' => 'Contraseña incorrecta'], 401);
    }
}
elseif ($accion === 'check_session') {
    if (isset($_SESSION['usuario'])) {
        $expiro = 3600;
        if (isset($_SESSION['created']) && (time() - $_SESSION['created'] > $expiro)) {
            session_destroy();
            respuestaJSON(['ok' => false, 'mensaje' => 'Sesión expirada']);
        }
        respuestaJSON(['ok' => true, 'csrf_token' => $_SESSION['csrf_token'] ?? '', 'usuario' => [
            'id' => $_SESSION['usuario']['id_usuario'],
            'nombre' => $_SESSION['usuario']['nombre_usuario'],
            'rol' => $_SESSION['usuario']['rol']
        ]]);
    } else {
        respuestaJSON(['ok' => false]);
    }
}
elseif ($accion === 'logout') {
    logAcceso($pdo, 'logout', "Usuario cerró sesión: " . ($_SESSION['usuario']['nombre_usuario'] ?? 'desconocido'));
    $_SESSION = [];
    session_destroy();
    respuestaJSON(['ok' => true]);
}