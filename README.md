# LinguaCampus - Sistema de Gestión de Escuela de Idiomas

Aplicación web para administrar estudiantes, docentes, idiomas, niveles, grupos, evaluaciones, certificados y asistencia. Desarrollada con **HTML5, CSS3, JavaScript Vanilla, PHP 8.0+ y MariaDB/MySQL**.

## Instalación y configuración

### Requisitos
- Servidor web con PHP 8.0+ (XAMPP, WAMP, Laragon)
- MariaDB 10.4+ / MySQL 8.0
- Apache con mod_headers habilitado (para security headers)

### Pasos
1. Clona o descarga este repositorio dentro de `htdocs` (XAMPP) como `LinguaCampus`.
2. Inicia los servicios **Apache** y **MySQL** desde el panel de control de XAMPP.
3. **Configura la conexión:**
   - Edita `api/conexion.php` si tu usuario/contraseña de MySQL es diferente.
   - Por defecto: usuario `root`, contraseña vacía (XAMPP).
4. **Inicializa la base de datos automáticamente:**
   - Abre en tu navegador: `http://localhost/LinguaCampus/api/check_db.php`
   - Este script crea la BD, todas las tablas (incluyendo `log_accesos`), índices compuestos y datos de prueba.
5. **Accede al sistema:**
   - `http://localhost/LinguaCampus/index.html`
   - Usuario: `admin` / Contraseña: `admin123`

## Mejoras de seguridad implementadas

- **Cifrado de contraseñas**: `password_hash(PASSWORD_BCRYPT)` en lugar de SHA2
- **Rate limiting**: Máximo 5 intentos de login fallidos en 5 minutos por usuario/IP
- **Tokens CSRF**: Protección contra Cross-Site Request Forgery en todas las peticiones POST
- **RBAC**: Control de acceso basado en roles (solo Administradores)
- **Sesiones seguras**: Cookies HttpOnly, SameSite=Strict, expiración a 1 hora, regeneración de ID
- **Validación en backend**: Correo (`filter_var`) y teléfono validados del lado del servidor
- **Transacciones**: Operaciones críticas (inscripciones, certificados) envueltas en transacciones
- **Logging**: Tabla `log_accesos` registra intentos de login, operaciones CRUD y errores
- **Security Headers**: CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- **Ocultación de errores**: En producción los errores PDO no se muestran al usuario
- **Índices compuestos**: 5 índices para optimizar consultas frecuentes

## Arquitectura del proyecto
- **Frontend SPA:** `index.html`, `css/mejoras.css`, `js/app.js`, `js/mejoras.js`
- **Backend API:** Archivos PHP en `api/` (CRUD para cada módulo + autenticación)
- **Base de datos:** MariaDB/MySQL con 9 tablas + tabla de logging
- **Reglas de negocio:** Implementadas en backend con validación adicional en frontend

## Módulos disponibles
- Login / Logout con protección contra fuerza bruta
- CRUD de estudiantes, docentes, idiomas, niveles, grupos
- Inscripciones con control de cupo (transaccional) y prerequisitos
- Evaluaciones con 4 módulos (Gramática, Conversación, Escritura, Comprensión auditiva)
- Emisión de certificados con verificación de promedio >= 3.5 y 4 módulos completos
- Calendario mensual con registro de asistencia
- Dashboard con estadísticas, gráficas y alertas
- Modo oscuro, exportación CSV, impresión, paginación, ordenamiento de columnas
- Perfil de estudiante con drawer lateral (inscripciones, notas, certificados)

## Reglas de negocio
- No se puede inscribir a un nivel sin haber aprobado el anterior (excepto A1).
- Control de cupo máximo por grupo con bloqueo transaccional (`FOR UPDATE`).
- Un docente no puede tener dos grupos en el mismo horario.
- No se eliminan idiomas, niveles, grupos, estudiantes o docentes si tienen registros dependientes activos.
- Solo se emiten certificados si el estudiante completó los 4 módulos y su promedio es >= 3.5.

## Licencia
Proyecto de uso académico.
