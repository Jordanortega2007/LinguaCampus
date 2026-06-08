# Pruebas de Caja Blanca - LinguaCampus

## 1. Función: Login (auth.php)
**Diagrama de flujo (texto):**
1. Recibir JSON con usuario y contraseña.
2. Consultar BD con SHA2(256).
3. ¿Usuario existe? → No → Error "Credenciales inválidas".
4. ¿Estado = 1? → No → Error "Cuenta inactiva".
5. Sí → Crear sesión, devolver datos del usuario.

**Casos de prueba:**
- Ruta 1: Usuario correcto, contraseña correcta, estado activo → Éxito.
- Ruta 2: Usuario incorrecto → Error.
- Ruta 3: Contraseña incorrecta → Error.
- Ruta 4: Usuario correcto, estado=0 → Error de cuenta inactiva.

## 2. Función: Inscripción (inscripciones.php)
**Diagrama de flujo (texto):**
1. Recibir id_estudiante e id_grupo.
2. Obtener datos del grupo (cupo, nivel, idioma).
3. Contar inscripciones activas en el grupo.
   - ¿Cupo lleno? → Sí → Error cupo máximo.
4. Si el nivel no es A1, verificar certificado del nivel anterior.
   - ¿Tiene certificado? → No → Error prerequisito.
5. Insertar inscripción (estado=1, fecha actual).

**Casos de prueba:**
- Ruta 1: Cupo disponible, prerequisito ok → Inscripción exitosa.
- Ruta 2: Cupo lleno → Error.
- Ruta 3: Nivel A1 sin prerequisito → Éxito (no verifica certificado).
- Ruta 4: Nivel B1 sin certificado A2 → Error.

## 3. Función: Emitir certificado (certificados.php)
**Diagrama de flujo (texto):**
1. Recibir id_estudiante e id_nivel.
2. Buscar inscripción activa del estudiante en un grupo de ese nivel.
   - ¿Existe? → No → Error "sin inscripción activa".
3. Contar módulos evaluados (deben ser 4).
   - ¿Menos de 4? → Error "faltan evaluaciones".
4. Calcular promedio de notas.
   - ¿Promedio < 3.5? → Error "promedio insuficiente".
5. Insertar certificado (fecha de hoy).

**Casos de prueba:**
- Ruta 1: Todas las condiciones cumplidas → Certificado emitido.
- Ruta 2: Sin inscripción activa → Error.
- Ruta 3: Evaluaciones incompletas → Error.
- Ruta 4: Promedio < 3.5 → Error.