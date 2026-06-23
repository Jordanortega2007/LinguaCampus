-- ============================================================
-- LinguaCampus - Script completo de base de datos
-- Compatible con MySQL 8.0 (MySQL Workbench)
-- Generado: junio 2026
-- ============================================================

CREATE DATABASE IF NOT EXISTS `linguacampus` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `linguacampus`;

-- ============================================================
-- TABLAS
-- ============================================================

DROP TABLE IF EXISTS `log_accesos`;
DROP TABLE IF EXISTS `asistencias`;
DROP TABLE IF EXISTS `evaluaciones`;
DROP TABLE IF EXISTS `certificados`;
DROP TABLE IF EXISTS `inscripciones`;
DROP TABLE IF EXISTS `grupos`;
DROP TABLE IF EXISTS `niveles`;
DROP TABLE IF EXISTS `idiomas`;
DROP TABLE IF EXISTS `docentes`;
DROP TABLE IF EXISTS `estudiantes`;
DROP TABLE IF EXISTS `usuarios`;

CREATE TABLE `usuarios` (
    `id_usuario` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre_usuario` VARCHAR(50) UNIQUE NOT NULL,
    `contraseña` VARCHAR(255) NOT NULL,
    `rol` VARCHAR(20) NOT NULL DEFAULT 'Administrador',
    `estado` TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE `estudiantes` (
    `id_estudiante` INT AUTO_INCREMENT PRIMARY KEY,
    `nombres` VARCHAR(50) NOT NULL,
    `apellidos` VARCHAR(50) NOT NULL,
    `documento` VARCHAR(20) UNIQUE NOT NULL,
    `telefono` VARCHAR(20),
    `correo` VARCHAR(100),
    `fecha_nacimiento` DATE,
    `estado` TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE `docentes` (
    `id_docente` INT AUTO_INCREMENT PRIMARY KEY,
    `nombres` VARCHAR(50) NOT NULL,
    `apellidos` VARCHAR(50) NOT NULL,
    `documento` VARCHAR(20) UNIQUE NOT NULL,
    `idioma_principal` VARCHAR(50) NOT NULL,
    `nivel_certificado` VARCHAR(10),
    `telefono` VARCHAR(20),
    `correo` VARCHAR(100),
    `estado` TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE `idiomas` (
    `id_idioma` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre_idioma` VARCHAR(50) UNIQUE NOT NULL,
    `descripcion` TEXT,
    `estado` TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE `niveles` (
    `id_nivel` INT AUTO_INCREMENT PRIMARY KEY,
    `id_idioma` INT NOT NULL,
    `nombre_nivel` VARCHAR(10) NOT NULL,
    FOREIGN KEY (`id_idioma`) REFERENCES `idiomas`(`id_idioma`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `grupos` (
    `id_grupo` INT AUTO_INCREMENT PRIMARY KEY,
    `id_idioma` INT NOT NULL,
    `id_nivel` INT NOT NULL,
    `id_docente` INT NOT NULL,
    `horario` VARCHAR(50) NOT NULL,
    `cupo_maximo` INT NOT NULL DEFAULT 15,
    `estado` TINYINT NOT NULL DEFAULT 1,
    FOREIGN KEY (`id_idioma`) REFERENCES `idiomas`(`id_idioma`),
    FOREIGN KEY (`id_nivel`) REFERENCES `niveles`(`id_nivel`),
    FOREIGN KEY (`id_docente`) REFERENCES `docentes`(`id_docente`)
) ENGINE=InnoDB;

CREATE TABLE `inscripciones` (
    `id_inscripcion` INT AUTO_INCREMENT PRIMARY KEY,
    `id_estudiante` INT NOT NULL,
    `id_grupo` INT NOT NULL,
    `fecha_inscripcion` DATE NOT NULL,
    `estado` TINYINT NOT NULL DEFAULT 1,
    FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes`(`id_estudiante`),
    FOREIGN KEY (`id_grupo`) REFERENCES `grupos`(`id_grupo`)
) ENGINE=InnoDB;

CREATE TABLE `evaluaciones` (
    `id_evaluacion` INT AUTO_INCREMENT PRIMARY KEY,
    `id_inscripcion` INT NOT NULL,
    `modulo` VARCHAR(50) NOT NULL,
    `nota` DECIMAL(3,2) NOT NULL,
    `fecha_evaluacion` DATE NOT NULL,
    FOREIGN KEY (`id_inscripcion`) REFERENCES `inscripciones`(`id_inscripcion`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `certificados` (
    `id_certificado` INT AUTO_INCREMENT PRIMARY KEY,
    `id_estudiante` INT NOT NULL,
    `id_nivel` INT NOT NULL,
    `fecha_emision` DATE NOT NULL,
    FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes`(`id_estudiante`),
    FOREIGN KEY (`id_nivel`) REFERENCES `niveles`(`id_nivel`)
) ENGINE=InnoDB;

CREATE TABLE `asistencias` (
    `id_asistencia` INT AUTO_INCREMENT PRIMARY KEY,
    `id_inscripcion` INT NOT NULL,
    `fecha` DATE NOT NULL,
    `presente` TINYINT NOT NULL DEFAULT 1,
    UNIQUE KEY `unique_ins_fecha` (`id_inscripcion`, `fecha`),
    FOREIGN KEY (`id_inscripcion`) REFERENCES `inscripciones`(`id_inscripcion`)
) ENGINE=InnoDB;

CREATE TABLE `log_accesos` (
    `id_log` INT AUTO_INCREMENT PRIMARY KEY,
    `id_usuario` INT NULL,
    `accion` VARCHAR(50) NOT NULL,
    `detalle` TEXT,
    `direccion_ip` VARCHAR(45),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_accion` (`accion`),
    INDEX `idx_usuario` (`id_usuario`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_ip_accion` (`direccion_ip`, `accion`, `created_at`)
) ENGINE=InnoDB;

-- ============================================================
-- ÍNDICES COMPUESTOS PARA RENDIMIENTO
-- ============================================================
CREATE INDEX `idx_inscripciones_estado_grupo` ON `inscripciones` (`id_grupo`, `estado`);
CREATE INDEX `idx_inscripciones_estudiante_estado` ON `inscripciones` (`id_estudiante`, `estado`);
CREATE INDEX `idx_evaluaciones_inscripcion` ON `evaluaciones` (`id_inscripcion`, `modulo`);
CREATE INDEX `idx_certificados_estudiante` ON `certificados` (`id_estudiante`, `id_nivel`);
CREATE INDEX `idx_grupos_docente_horario` ON `grupos` (`id_docente`, `horario`, `estado`);

-- ============================================================
-- DATOS SEMILLA
-- ============================================================

-- Usuario administrador (contraseña: admin123 con bcrypt)
INSERT INTO `usuarios` (`nombre_usuario`, `contraseña`, `rol`, `estado`) VALUES
('admin', '$2y$10$ljqN2z1RkeI7342nzM6Lf..AHqBcksrJpPWsgKx4FD7m416RYllCy', 'Administrador', 1);

-- Idiomas
INSERT INTO `idiomas` (`nombre_idioma`, `descripcion`, `estado`) VALUES
('Inglés', 'Curso global de inglés norteamericano y británico', 1),
('Francés', 'Curso estructurado de francés estándar', 1),
('Alemán', 'Curso de alemán comercial y general', 1);

-- Niveles (A1 a C2 para cada idioma)
INSERT INTO `niveles` (`id_idioma`, `nombre_nivel`) VALUES
(1, 'A1'), (1, 'A2'), (1, 'B1'), (1, 'B2'), (1, 'C1'), (1, 'C2'),
(2, 'A1'), (2, 'A2'), (2, 'B1'), (2, 'B2'), (2, 'C1'), (2, 'C2'),
(3, 'A1'), (3, 'A2'), (3, 'B1'), (3, 'B2'), (3, 'C1'), (3, 'C2');

-- Docentes
INSERT INTO `docentes` (`nombres`, `apellidos`, `documento`, `idioma_principal`, `nivel_certificado`, `telefono`, `correo`, `estado`) VALUES
('John', 'Doe', 'DOC12345', 'Inglés', 'C2', '555-0199', 'john.doe@linguacampus.edu', 1),
('Marie', 'Dupont', 'DOC67890', 'Francés', 'C1', '555-0244', 'marie.dupont@linguacampus.edu', 1);

-- Estudiantes
INSERT INTO `estudiantes` (`nombres`, `apellidos`, `documento`, `telefono`, `correo`, `fecha_nacimiento`, `estado`) VALUES
('Juan', 'Pérez', 'EST11111', '555-9001', 'juan.perez@email.com', '2000-05-15', 1),
('Ana', 'Gómez', 'EST22222', '555-9002', 'ana.gomez@email.com', '2001-09-20', 1);
