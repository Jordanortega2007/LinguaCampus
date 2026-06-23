<?php
// ============================================================
// LinguaCampus - Script de Diagnóstico e Inicialización de BD
// ============================================================
header('Content-Type: text/html; charset=utf-8');

// Leer variables de conexión desde conexion.php mediante regex para no abortar si falla la conexión inicial
$conexion_content = file_get_contents(__DIR__ . '/conexion.php');
preg_match('/\$host\s*=\s*[\'"]([^\'"]+)[\'"]/', $conexion_content, $m_host);
preg_match('/\$db\s*=\s*[\'"]([^\'"]+)[\'"]/', $conexion_content, $m_db);
preg_match('/\$usuario\s*=\s*[\'"]([^\'"]+)[\'"]/', $conexion_content, $m_user);
preg_match('/\$clave\s*=\s*[\'"]([^\'"]*)[\'"]/', $conexion_content, $m_pass);

$host = $m_host[1] ?? 'localhost';
$db_name = $m_db[1] ?? 'linguacampus';
$usuario = $m_user[1] ?? 'root';
$clave = $m_pass[1] ?? '';

$conn_status = [];
$step_success = true;

// 1. Intentar conectar a MySQL (general)
try {
    $pdo_raw = new PDO("mysql:host=$host;charset=utf8mb4", $usuario, $clave, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $conn_status[] = [
        'title' => 'Conexión a MySQL',
        'status' => 'OK',
        'desc' => "Conectado exitosamente al servidor MySQL en '$host' con usuario '$usuario'."
    ];
} catch (PDOException $e) {
    $conn_status[] = [
        'title' => 'Conexión a MySQL',
        'status' => 'ERROR',
        'desc' => "No se pudo conectar a MySQL: " . $e->getMessage() . ". Verifica que MySQL esté activo en el Panel de XAMPP."
    ];
    $step_success = false;
}

if ($step_success) {
    // 2. Crear BD si no existe
    try {
        $pdo_raw->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn_status[] = [
            'title' => 'Base de Datos',
            'status' => 'OK',
            'desc' => "Base de datos '$db_name' verificada/creada con éxito."
        ];
    } catch (PDOException $e) {
        $conn_status[] = [
            'title' => 'Base de Datos',
            'status' => 'ERROR',
            'desc' => "Error al crear la base de datos '$db_name': " . $e->getMessage()
        ];
        $step_success = false;
    }
}

if ($step_success) {
    // 3. Conectar a la base de datos específica
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $usuario, $clave, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $conn_status[] = [
            'title' => 'Conexión a la BD LinguaCampus',
            'status' => 'OK',
            'desc' => "Conexión establecida con la base de datos '$db_name'."
        ];
    } catch (PDOException $e) {
        $conn_status[] = [
            'title' => 'Conexión a la BD LinguaCampus',
            'status' => 'ERROR',
            'desc' => "Error al seleccionar la base de datos '$db_name': " . $e->getMessage()
        ];
        $step_success = false;
    }
}

if ($step_success) {
    // 4. Crear tablas e insertar datos semilla
    $queries = [
        'usuarios' => "CREATE TABLE IF NOT EXISTS usuarios (
            id_usuario INT AUTO_INCREMENT PRIMARY KEY,
            nombre_usuario VARCHAR(50) UNIQUE NOT NULL,
            contraseña VARCHAR(64) NOT NULL,
            rol VARCHAR(20) NOT NULL,
            estado TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB",
        
        'estudiantes' => "CREATE TABLE IF NOT EXISTS estudiantes (
            id_estudiante INT AUTO_INCREMENT PRIMARY KEY,
            nombres VARCHAR(50) NOT NULL,
            apellidos VARCHAR(50) NOT NULL,
            documento VARCHAR(20) UNIQUE NOT NULL,
            telefono VARCHAR(20),
            correo VARCHAR(100),
            fecha_nacimiento DATE,
            estado TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB",
        
        'docentes' => "CREATE TABLE IF NOT EXISTS docentes (
            id_docente INT AUTO_INCREMENT PRIMARY KEY,
            nombres VARCHAR(50) NOT NULL,
            apellidos VARCHAR(50) NOT NULL,
            documento VARCHAR(20) UNIQUE NOT NULL,
            idioma_principal VARCHAR(50) NOT NULL,
            nivel_certificado VARCHAR(10),
            telefono VARCHAR(20),
            correo VARCHAR(100),
            estado TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB",
        
        'idiomas' => "CREATE TABLE IF NOT EXISTS idiomas (
            id_idioma INT AUTO_INCREMENT PRIMARY KEY,
            nombre_idioma VARCHAR(50) UNIQUE NOT NULL,
            descripcion TEXT,
            estado TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB",
        
        'niveles' => "CREATE TABLE IF NOT EXISTS niveles (
            id_nivel INT AUTO_INCREMENT PRIMARY KEY,
            id_idioma INT NOT NULL,
            nombre_nivel VARCHAR(10) NOT NULL,
            FOREIGN KEY (id_idioma) REFERENCES idiomas(id_idioma) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        
        'grupos' => "CREATE TABLE IF NOT EXISTS grupos (
            id_grupo INT AUTO_INCREMENT PRIMARY KEY,
            id_idioma INT NOT NULL,
            id_nivel INT NOT NULL,
            id_docente INT NOT NULL,
            horario VARCHAR(50) NOT NULL,
            cupo_maximo INT NOT NULL DEFAULT 15,
            estado TINYINT NOT NULL DEFAULT 1,
            FOREIGN KEY (id_idioma) REFERENCES idiomas(id_idioma),
            FOREIGN KEY (id_nivel) REFERENCES niveles(id_nivel),
            FOREIGN KEY (id_docente) REFERENCES docentes(id_docente)
        ) ENGINE=InnoDB",
        
        'inscripciones' => "CREATE TABLE IF NOT EXISTS inscripciones (
            id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
            id_estudiante INT NOT NULL,
            id_grupo INT NOT NULL,
            fecha_inscripcion DATE NOT NULL,
            estado TINYINT NOT NULL DEFAULT 1,
            FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante),
            FOREIGN KEY (id_grupo) REFERENCES grupos(id_grupo)
        ) ENGINE=InnoDB",
        
        'evaluaciones' => "CREATE TABLE IF NOT EXISTS evaluaciones (
            id_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
            id_inscripcion INT NOT NULL,
            modulo VARCHAR(50) NOT NULL,
            nota DECIMAL(3,2) NOT NULL,
            fecha_evaluacion DATE NOT NULL,
            FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        
        'certificados' => "CREATE TABLE IF NOT EXISTS certificados (
            id_certificado INT AUTO_INCREMENT PRIMARY KEY,
            id_estudiante INT NOT NULL,
            id_nivel INT NOT NULL,
            fecha_emision DATE NOT NULL,
            FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante),
            FOREIGN KEY (id_nivel) REFERENCES niveles(id_nivel)
        ) ENGINE=InnoDB",
        
        'asistencias' => "CREATE TABLE IF NOT EXISTS asistencias (
            id_asistencia INT AUTO_INCREMENT PRIMARY KEY,
            id_inscripcion INT NOT NULL,
            fecha DATE NOT NULL,
            presente TINYINT NOT NULL DEFAULT 1,
            UNIQUE KEY unique_ins_fecha (id_inscripcion, fecha),
            FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion)
        ) ENGINE=InnoDB",

        'log_accesos' => "CREATE TABLE IF NOT EXISTS log_accesos (
            id_log INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NULL,
            accion VARCHAR(50) NOT NULL,
            detalle TEXT,
            direccion_ip VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_accion (accion),
            INDEX idx_usuario (id_usuario),
            INDEX idx_created (created_at),
            INDEX idx_ip_accion (direccion_ip, accion, created_at)
        ) ENGINE=InnoDB"
    ];

    // Índices compuestos para mejorar rendimiento
    $indices = [
        "CREATE INDEX IF NOT EXISTS idx_inscripciones_estado_grupo ON inscripciones (id_grupo, estado)",
        "CREATE INDEX IF NOT EXISTS idx_inscripciones_estudiante_estado ON inscripciones (id_estudiante, estado)",
        "CREATE INDEX IF NOT EXISTS idx_evaluaciones_inscripcion ON evaluaciones (id_inscripcion, modulo)",
        "CREATE INDEX IF NOT EXISTS idx_certificados_estudiante ON certificados (id_estudiante, id_nivel)",
        "CREATE INDEX IF NOT EXISTS idx_grupos_docente_horario ON grupos (id_docente, horario, estado)",
    ];
    foreach ($indices as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* ignorar si ya existen */ }
    }

    foreach ($queries as $table_name => $sql) {
        try {
            $pdo->exec($sql);
            $conn_status[] = [
                'title' => "Tabla: $table_name",
                'status' => 'OK',
                'desc' => "Tabla '$table_name' verificada/creada exitosamente."
            ];
        } catch (PDOException $e) {
            $conn_status[] = [
                'title' => "Tabla: $table_name",
                'status' => 'ERROR',
                'desc' => "No se pudo crear la tabla '$table_name': " . $e->getMessage()
            ];
            $step_success = false;
        }
    }
}

if ($step_success) {
    // 5. Semilla de Administrador
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE nombre_usuario = 'admin'");
        $stmt->execute();
        $admin_exists = $stmt->fetchColumn() > 0;
        
        $hashAdmin = password_hash('admin123', PASSWORD_BCRYPT);
        if (!$admin_exists) {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre_usuario, contraseña, rol, estado) VALUES ('admin', ?, 'Administrador', 1)");
            $stmt->execute([$hashAdmin]);
            $conn_status[] = [
                'title' => 'Usuario Administrador Semilla',
                'status' => 'OK',
                'desc' => "Se ha creado el usuario por defecto: <strong>admin</strong> con contraseña: <strong>admin123</strong>."
            ];
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET estado = 1, contraseña = ?, rol = 'Administrador' WHERE nombre_usuario = 'admin'");
            $stmt->execute([$hashAdmin]);
            $conn_status[] = [
                'title' => 'Usuario Administrador Verificado',
                'status' => 'OK',
                'desc' => "El usuario 'admin' existe, se ha reactivado y su contraseña se ha migrado a bcrypt (password_hash)."
            ];
        }
    } catch (PDOException $e) {
        $conn_status[] = [
            'title' => 'Usuario Administrador',
            'status' => 'ERROR',
            'desc' => "Error al verificar/crear el usuario administrador: " . $e->getMessage()
        ];
        $step_success = false;
    }
}

if ($step_success) {
    // 6. Semilla de datos adicionales si las tablas están vacías
    try {
        // Verificar idiomas
        $stmt = $pdo->query("SELECT COUNT(*) FROM idiomas");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO idiomas (nombre_idioma, descripcion, estado) VALUES
                ('Inglés', 'Curso global de inglés norteamericano y británico', 1),
                ('Francés', 'Curso estructurado de francés estándar', 1),
                ('Alemán', 'Curso de alemán comercial y general', 1)");
            $conn_status[] = [
                'title' => 'Datos Semilla: Idiomas',
                'status' => 'OK',
                'desc' => 'Idiomas iniciales (Inglés, Francés, Alemán) creados.'
            ];
            
            // Niveles
            $pdo->exec("INSERT INTO niveles (id_idioma, nombre_nivel) VALUES
                (1, 'A1'), (1, 'A2'), (1, 'B1'), (1, 'B2'), (1, 'C1'), (1, 'C2'),
                (2, 'A1'), (2, 'A2'), (2, 'B1'), (2, 'B2'), (2, 'C1'), (2, 'C2'),
                (3, 'A1'), (3, 'A2'), (3, 'B1'), (3, 'B2'), (3, 'C1'), (3, 'C2')");
            $conn_status[] = [
                'title' => 'Datos Semilla: Niveles',
                'status' => 'OK',
                'desc' => 'Niveles A1-C2 creados para todos los idiomas.'
            ];
        }

        // Verificar docentes
        $stmt = $pdo->query("SELECT COUNT(*) FROM docentes");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO docentes (nombres, apellidos, documento, idioma_principal, nivel_certificado, telefono, correo, estado) VALUES
                ('John', 'Doe', 'DOC12345', 'Inglés', 'C2', '555-0199', 'john.doe@linguacampus.edu', 1),
                ('Marie', 'Dupont', 'DOC67890', 'Francés', 'C1', '555-0244', 'marie.dupont@linguacampus.edu', 1)");
            $conn_status[] = [
                'title' => 'Datos Semilla: Docentes',
                'status' => 'OK',
                'desc' => 'Docentes iniciales creados.'
            ];
        }

        // Verificar estudiantes
        $stmt = $pdo->query("SELECT COUNT(*) FROM estudiantes");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO estudiantes (nombres, apellidos, documento, telefono, correo, fecha_nacimiento, estado) VALUES
                ('Juan', 'Pérez', 'EST11111', '555-9001', 'juan.perez@email.com', '2000-05-15', 1),
                ('Ana', 'Gómez', 'EST22222', '555-9002', 'ana.gomez@email.com', '2001-09-20', 1)");
            $conn_status[] = [
                'title' => 'Datos Semilla: Estudiantes',
                'status' => 'OK',
                'desc' => 'Estudiantes iniciales creados.'
            ];
        }
        
    } catch (PDOException $e) {
        $conn_status[] = [
            'title' => 'Datos Semilla Opcionales',
            'status' => 'WARNING',
            'desc' => "No se pudieron insertar algunos datos semilla adicionales: " . $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Base de Datos - LinguaCampus</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #005f73;
            --secondary: #0a9396;
            --dark: #001219;
            --success: #2a9d8f;
            --error: #e63946;
            --warning: #f4a261;
            --bg: #f8f9fa;
            --card-bg: #ffffff;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--dark);
            padding: 2rem 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 750px;
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }
        header {
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 100%);
            color: white;
            padding: 2.5rem;
            text-align: center;
            position: relative;
        }
        header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }
        header p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }
        .content {
            padding: 2.5rem;
        }
        .status-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2.5rem;
        }
        .status-item {
            display: flex;
            align-items: flex-start;
            padding: 1.25rem;
            border-radius: 12px;
            background: #fdfdfd;
            border: 1px solid #eaeaea;
            transition: all 0.3s ease;
        }
        .status-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }
        .status-badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            text-transform: uppercase;
            margin-right: 1rem;
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }
        .status-badge.ok {
            background: rgba(42, 157, 143, 0.15);
            color: var(--success);
        }
        .status-badge.error {
            background: rgba(230, 57, 70, 0.15);
            color: var(--error);
        }
        .status-badge.warning {
            background: rgba(244, 162, 97, 0.15);
            color: var(--warning);
        }
        .status-info {
            flex-grow: 1;
        }
        .status-title {
            font-weight: 600;
            font-size: 1.05rem;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        .status-desc {
            font-size: 0.92rem;
            color: #555;
            line-height: 1.4;
        }
        .actions {
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 0.9rem 2.2rem;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.05rem;
            transition: background 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 15px rgba(0, 95, 115, 0.2);
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: var(--secondary);
            transform: translateY(-1px);
        }
        .btn:active {
            transform: translateY(1px);
        }
        .summary-banner {
            padding: 1.25rem;
            border-radius: 12px;
            text-align: center;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
        .summary-banner.success {
            background: rgba(42, 157, 143, 0.1);
            color: #1b665c;
            border: 1px solid rgba(42, 157, 143, 0.2);
        }
        .summary-banner.error {
            background: rgba(230, 57, 70, 0.1);
            color: #9e2a2b;
            border: 1px solid rgba(230, 57, 70, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🏫 Diagnóstico LinguaCampus</h1>
            <p>Estado de Conexión y Base de Datos</p>
        </header>
        <div class="content">
            <?php if ($step_success): ?>
                <div class="summary-banner success">
                    ✅ ¡La base de datos y el usuario Administrador están configurados correctamente!
                </div>
            <?php else: ?>
                <div class="summary-banner error">
                    ❌ Se encontraron algunos problemas durante el diagnóstico.
                </div>
            <?php endif; ?>

            <div class="status-list">
                <?php foreach ($conn_status as $item): ?>
                    <div class="status-item">
                        <span class="status-badge <?php echo strtolower($item['status']); ?>">
                            <?php echo $item['status']; ?>
                        </span>
                        <div class="status-info">
                            <div class="status-title"><?php echo $item['title']; ?></div>
                            <div class="status-desc"><?php echo $item['desc']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="actions">
                <a href="../index.html" class="btn">Ir al Inicio de Sesión</a>
            </div>
        </div>
    </div>
</body>
</html>
