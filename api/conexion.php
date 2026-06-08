<?php
// ============================================================
// LinguaCampus - Conexión a MySQL usando PDO
// Compatible con MySQL 8.0 (Workbench)
// Cambia las credenciales según tu entorno
// ============================================================

$host    = 'localhost';          // o 127.0.0.1
$db      = 'linguacampus';      // nombre de la base de datos
$usuario = 'root';               // tu usuario de MySQL
$clave   = '';                   // contraseña (vacía en XAMPP por defecto)
$charset = 'utf8mb4';            // para soporte de caracteres

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lanza excepciones si hay error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // devuelve arrays asociativos
    PDO::ATTR_EMULATE_PREPARES   => false,                    // usa preparaciones nativas
];

try {
    $pdo = new PDO($dsn, $usuario, $clave, $opciones);
} catch (PDOException $e) {
    // En producción no mostrarías el error detallado, pero para desarrollo sirve
    http_response_code(500);
    echo json_encode(['error' => 'Error al conectar con MySQL: ' . $e->getMessage()]);
    exit;
}