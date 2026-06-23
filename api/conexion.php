<?php
// ============================================================
// LinguaCampus - Conexión a MySQL usando PDO
// Compatible con MySQL 8.0 (Workbench)
// Cambia las credenciales según tu entorno
// ============================================================

define('IN_PRODUCTION', false);

// Configuración de sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', IN_PRODUCTION ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.use_strict_mode', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host    = 'localhost';
$db      = 'linguacampus';
$usuario = 'root';
$clave   = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$opciones = [
    PDO::ATTR_ERRMODE            => IN_PRODUCTION ? PDO::ERRMODE_SILENT : PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $usuario, $clave, $opciones);
} catch (PDOException $e) {
    http_response_code(500);
    $msg = IN_PRODUCTION ? 'Error interno del servidor' : 'Error al conectar con MySQL: ' . $e->getMessage();
    echo json_encode(['error' => $msg]);
    exit;
}

function validarCorreo($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validarTelefono($telefono) {
    return preg_match('/^[0-9+\-\s()]{7,20}$/', $telefono) === 1;
}

function generarCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificarCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requerirCSRF() {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['csrf_token'] ?? '';
    if (!verificarCSRFToken($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido']);
        exit;
    }
}

function requerirRol($rolesPermitidos = ['Administrador']) {
    if (!isset($_SESSION['usuario'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
    if (!in_array($_SESSION['usuario']['rol'], $rolesPermitidos)) {
        http_response_code(403);
        echo json_encode(['error' => 'Permisos insuficientes']);
        exit;
    }
}

function logAcceso($pdo, $accion, $detalle = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO log_accesos (id_usuario, accion, detalle, direccion_ip) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['usuario']['id_usuario'] ?? null,
            $accion,
            $detalle,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (PDOException $e) {
        // Silencioso - no debe interrumpir la operación principal
    }
}

function respuestaJSON($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos);
    exit;
}