# Pruebas de Caja Negra - LinguaCampus

| ID   | Caso de prueba               | Entrada                                    | Resultado esperado                             | Estado |
|------|-------------------------------|--------------------------------------------|------------------------------------------------|--------|
| CN01 | Login correcto                | Usuario: admin, Contraseña: admin123       | Acceso al dashboard, menú lateral visible      | ✅     |
| CN02 | Contraseña incorrecta         | Usuario: admin, Contraseña: 12345          | Mensaje: "Credenciales inválidas o cuenta inactiva" | ✅     |
| CN03 | Usuario inactivo              | Usuario inactivo (cambiar estado a 0)      | Mensaje de error                               | ✅     |
| CN04 | Inscribir estudiante en grupo lleno | Estudiante nuevo en grupo con cupo máximo 2, ya tiene 2 inscritos | Error: "El grupo ha alcanzado su cupo máximo" | ✅     |
| CN05 | Inscribir estudiante sin prerequisito | Estudiante sin certificado A1 intenta inscribirse en A2 | Error: "El estudiante no ha aprobado el nivel anterior requerido" | ✅     |
| CN06 | Emitir certificado con promedio < 3.5 | Estudiante con notas: 3.0, 3.2, 2.8, 3.5 (promedio 3.125) | Error: "Promedio insuficiente"                 | ✅     |
| CN07 | Emitir certificado sin evaluaciones completas | Estudiante con solo 2 módulos evaluados | Error: "Faltan evaluaciones"                   | ✅     |
| CN08 | Registrar evaluación válida   | Nota 4.5, módulo "Gramática", inscripción válida | Guardado exitoso, aparece en la tabla         | ✅     |
| CN09 | Eliminar idioma con grupos asociados | Idioma "Inglés" con grupos activos       | Error: "No se puede eliminar el idioma porque tiene grupos asociados" | ✅     |
| CN10 | Docente con cruce de horario  | Intentar asignar mismo docente, mismo horario en dos grupos | Error: "El docente ya tiene un grupo en ese horario" | ✅     |