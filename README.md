# LinguaCampus - Sistema de Gestión de Escuela de Idiomas

Aplicación web sencilla para administrar estudiantes, docentes, idiomas, niveles, grupos, evaluaciones y certificados. Desarrollada con **HTML5, CSS3, JavaScript Vanilla, PHP (API mínima) y MySQL 8.0**.

## 🚀 Instalación y configuración

### Requisitos
- Servidor web con PHP 7.4+ (XAMPP, WAMP, Laragon)
- MySQL 8.0 (incluido en XAMPP/WAMP o independiente)
- MySQL Workbench 8.0 CE (para importar la base de datos)

### Pasos
1. Clona o descarga este repositorio.
2. **Crea la base de datos:**
   - Abre MySQL Workbench y ejecuta el script `sql/linguacampus.sql`.
   - Este script crea la base de datos `linguacampus` con todas las tablas y datos de prueba.
3. **Configura la conexión:**
   - Edita el archivo `api/conexion.php` y ajusta las variables `$usuario` y `$clave` según tu entorno MySQL.
   - Por defecto: usuario `root`, contraseña vacía (XAMPP).
4. **Copia el proyecto a tu servidor web:**
   - Coloca la carpeta `linguacampus` dentro de `htdocs` (si usas XAMPP) o la raíz pública de tu servidor.
5. **Inicia los servicios Apache y MySQL** desde el panel de control de XAMPP/WAMP.
6. **Accede al sistema:**
   - Abre tu navegador y ve a `http://localhost/linguacampus/index.html`.
   - Usuario de prueba: `admin` / Contraseña: `admin123`.

## 🧱 Arquitectura del proyecto
- **Frontend SPA:** `index.html`, `css/estilo.css`, `js/app.js`
- **Backend API:** Archivos PHP en la carpeta `api/` que actúan como intermediarios con MySQL.
- **Base de datos:** MySQL, con script en `sql/linguacampus.sql`.
- **Reglas de negocio** implementadas tanto en el frontend (validaciones previas) como en el backend (validación definitiva).

## 📋 Módulos disponibles
- Login / Logout
- CRUD de estudiantes, docentes, idiomas, niveles, grupos
- Inscripciones con control de cupo y prerequisitos
- Evaluaciones con módulos (Gramática, Conversación, Escritura, Comprensión auditiva)
- Emisión de certificados con promedio mínimo y módulos completos

## ✅ Funcionalidades de negocio
- No se puede inscribir a un nivel sin haber aprobado el anterior (excepto A1).
- Control de cupo máximo por grupo.
- Un docente no puede tener dos grupos en el mismo horario.
- No se eliminan idiomas si tienen grupos asociados.
- Solo se emiten certificados si el estudiante completó los 4 módulos y su promedio es ≥ 3.5.

## 🧪 Pruebas
Los casos de prueba de caja negra y caja blanca se encuentran en la carpeta `pruebas/`.

## 📄 Licencia
Este proyecto es de uso académico. Siéntete libre de modificarlo y adaptarlo.