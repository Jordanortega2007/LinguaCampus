// ============================================================
// LinguaCampus - SPA Completa con Calendario y Asistencia
// ============================================================
let usuarioActual = null;
let csrfToken = '';
const API = 'api/';
let callbackEliminar = null;
let grupoSeleccionado = null;
let fechaSeleccionada = new Date().toISOString().split('T')[0];

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('form-login').addEventListener('submit', login);
    document.getElementById('btn-logout').addEventListener('click', logout);
    document.querySelectorAll('#menu-lateral a').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            cargarModulo(link.getAttribute('data-modulo'));
        });
    });
    document.getElementById('modal-aceptar').addEventListener('click', () => {
        document.getElementById('modal-confirm').classList.remove('activo');
        if (callbackEliminar) callbackEliminar();
    });
    document.getElementById('modal-cancelar').addEventListener('click', () => {
        document.getElementById('modal-confirm').classList.remove('activo');
    });
    document.getElementById('btn-toggle-dark-mode').addEventListener('click', toggleDarkMode);
    applySavedTheme(); // Aplica el tema guardado al cargar
    verificarSesion(); // Verificar si ya tiene sesión activa

    // Limpiar errores visuales del login al escribir
    document.getElementById('login-usuario').addEventListener('input', e => {
        e.target.classList.remove('input-error');
        document.getElementById('login-error').textContent = '';
    });
    document.getElementById('login-password').addEventListener('input', e => {
        e.target.classList.remove('input-error');
        document.getElementById('login-error').textContent = '';
    });
});

// ---------- AUTENTICACIÓN ----------
async function login(e) {
    e.preventDefault();
    const inputUsuario = document.getElementById('login-usuario');
    const inputPassword = document.getElementById('login-password');
    const usuario = inputUsuario.value.trim();
    const password = inputPassword.value.trim();
    const errorDiv = document.getElementById('login-error');
    
    // Limpiar clases de error previas
    inputUsuario.classList.remove('input-error');
    inputPassword.classList.remove('input-error');
    errorDiv.textContent = '';

    if (!usuario) {
        inputUsuario.classList.add('input-error');
        errorDiv.textContent = 'El usuario es obligatorio.';
        inputUsuario.focus();
        return;
    }
    if (usuario.length < 3) {
        inputUsuario.classList.add('input-error');
        errorDiv.textContent = 'El usuario debe tener al menos 3 caracteres.';
        inputUsuario.focus();
        return;
    }
    if (!password) {
        inputPassword.classList.add('input-error');
        errorDiv.textContent = 'La contraseña es obligatoria.';
        inputPassword.focus();
        return;
    }
    if (password.length < 5) {
        inputPassword.classList.add('input-error');
        errorDiv.textContent = 'La contraseña debe tener al menos 5 caracteres.';
        inputPassword.focus();
        return;
    }

    try {
        const resp = await fetch(API + 'auth.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'login', usuario, password })
        });
        
        if (!resp.ok) {
            const errorData = await resp.json().catch(() => ({}));
            throw new Error(errorData.error || `Error del servidor (${resp.status})`);
        }

        const data = await resp.json();
        if (data.ok) {
            usuarioActual = data.usuario;
            csrfToken = data.csrf_token || '';
            document.getElementById('login-panel').style.display = 'none';
            document.getElementById('dashboard').style.display = 'block';
            document.getElementById('usuario-activo').textContent = `Bienvenido, ${usuarioActual.nombre}`;
            cargarModulo('estudiantes');
        } else {
            errorDiv.textContent = data.mensaje || 'Error desconocido';
            if (resp.status === 429) {
                errorDiv.textContent = data.mensaje;
            }
        }
    } catch (error) {
        console.error('Error detallado:', error);
        if (error.message.includes('Unexpected token')) {
            errorDiv.textContent = 'Error: El servidor no devolvió un JSON válido. Revisa los logs de PHP.';
        } else {
            errorDiv.textContent = error.message === 'Failed to fetch' 
                ? 'Error de conexión. ¿Está corriendo el servidor?' 
                : error.message;
        }
    }
}

async function verificarSesion() {
    try {
        const resp = await fetch(API + 'auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'check_session' })
        });
        if (resp.ok) {
            const data = await resp.json();
            if (data.ok) {
                usuarioActual = data.usuario;
                csrfToken = data.csrf_token || '';
                document.getElementById('login-panel').style.display = 'none';
                document.getElementById('dashboard').style.display = 'block';
                document.getElementById('usuario-activo').textContent = `Bienvenido, ${usuarioActual.nombre}`;
                cargarModulo('estudiantes');
            }
        }
    } catch (error) {
        console.error('Error al verificar sesión activa:', error);
    }
}

async function logout() {
    await fetch(API + 'auth.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'logout' }) }).catch(() => {});
    usuarioActual = null;
    location.reload();
}

// ---------- MODO OSCURO ----------
function toggleDarkMode() {
    const body = document.body;
    const currentTheme = body.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    body.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
}

function applySavedTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light'; // Por defecto, modo claro
    document.body.setAttribute('data-theme', savedTheme);
}

// ---------- CARGA DE MÓDULOS ----------
async function cargarModulo(modulo) {
    const contenedor = document.getElementById('contenido-principal');
    contenedor.innerHTML = '<p>Cargando...</p>';
    
    // Resaltar el botón activo en el menú lateral
    document.querySelectorAll('#menu-lateral a').forEach(link => {
        link.classList.toggle('activo', link.getAttribute('data-modulo') === modulo);
    });

    if (modulo === 'calendario') { renderizarCalendario(); return; }
    try {
        const resp = await fetch(`${API}${modulo}.php?accion=listar`);
        const datos = await resp.json();
        if (!Array.isArray(datos)) throw new Error('Formato incorrecto');
        switch (modulo) {
            case 'estudiantes': renderizarEstudiantes(datos); break;
            case 'docentes': renderizarDocentes(datos); break;
            case 'idiomas': renderizarIdiomas(datos); break;
            case 'niveles': await renderizarNiveles(datos); break; // Ya era async, pero lo reafirmo
            case 'grupos': await renderizarGrupos(datos); break; // Ahora esperamos a que renderizarGrupos termine
            case 'inscripciones': await renderizarInscripciones(datos); break; // Aseguramos que espere
            case 'evaluaciones': await renderizarEvaluaciones(datos); break; // Aseguramos que espere
            case 'certificados': await renderizarCertificados(datos); break; // Aseguramos que espere
            default: contenedor.innerHTML = '<p>Módulo no encontrado</p>';
        }
    } catch (e) {
        contenedor.innerHTML = `<p class="error">Error al cargar: ${e.message}</p>`;
    }
}

// ---------- RENDERIZADORES DE MÓDULOS ----------
function renderizarEstudiantes(lista) {
    window.estudiantesData = lista;
    let html = `<h2>👨‍🎓 Estudiantes</h2>
    <form class="form-crud" id="form-estudiantes">
        <input type="hidden" name="id_estudiante">
        <input name="nombres" placeholder="Nombres" required>
        <input name="apellidos" placeholder="Apellidos" required>
        <input name="documento" placeholder="Documento" required pattern="\\d+" title="Ingrese solo números" inputmode="numeric">
        <input name="telefono" placeholder="Teléfono">
        <input name="correo" placeholder="Correo" type="email">
        <input name="fecha_nacimiento" type="date">
        <button type="submit">Guardar</button>
    </form>
    <input type="text" id="busqueda-estudiantes" placeholder="Buscar..." class="input-busqueda">`;
    html += generarTabla(lista, ['id_estudiante','documento','nombres','apellidos','correo','estado'], 'estudiantes');
    document.getElementById('contenido-principal').innerHTML = html;
    document.getElementById('form-estudiantes').addEventListener('submit', e => guardarEntidad(e, 'estudiantes'));
    document.getElementById('busqueda-estudiantes').addEventListener('input', e => filtrarTabla('estudiantes', e.target.value));
}

function renderizarDocentes(lista) {
    window.docentesData = lista;
    let html = `<h2>👩‍🏫 Docentes</h2>
    <form class="form-crud" id="form-docentes">
        <input type="hidden" name="id_docente">
        <input name="nombres" placeholder="Nombres" required>
        <input name="apellidos" placeholder="Apellidos" required>
        <input name="documento" placeholder="Número Identificación" required pattern="\\d+" title="Ingrese solo números" inputmode="numeric">
        <input name="idioma_principal" placeholder="Idioma principal" required>
        <input name="nivel_certificado" placeholder="Nivel certificado" required>
        <input name="telefono" placeholder="Teléfono">
        <input name="correo" placeholder="Correo" type="email">
        <button type="submit">Guardar</button>
    </form>
    <input type="text" id="busqueda-docentes" placeholder="Buscar..." class="input-busqueda">`;
    html += generarTabla(lista, ['id_docente','documento','nombres','apellidos','idioma_principal','nivel_certificado','correo','estado'], 'docentes');
    document.getElementById('contenido-principal').innerHTML = html;
    document.getElementById('form-docentes').addEventListener('submit', e => guardarEntidad(e, 'docentes'));
    document.getElementById('busqueda-docentes').addEventListener('input', e => filtrarTabla('docentes', e.target.value));
}

function renderizarIdiomas(lista) {
    window.idiomasData = lista;
    let html = `<h2>🌐 Idiomas</h2>
    <form class="form-crud" id="form-idiomas">
        <input type="hidden" name="id_idioma">
        <input name="nombre_idioma" placeholder="Nombre" required>
        <textarea name="descripcion" placeholder="Descripción"></textarea>
        <button type="submit">Guardar</button>
    </form>
    <input type="text" id="busqueda-idiomas" placeholder="Buscar..." class="input-busqueda">`;
    html += generarTabla(lista, ['id_idioma','nombre_idioma','descripcion','estado'], 'idiomas');
    document.getElementById('contenido-principal').innerHTML = html;
    document.getElementById('form-idiomas').addEventListener('submit', e => guardarEntidad(e, 'idiomas'));
    document.getElementById('busqueda-idiomas').addEventListener('input', e => filtrarTabla('idiomas', e.target.value));
}

async function renderizarNiveles(lista) {
    window.nivelesData = lista;
    let html = `<h2>📊 Niveles</h2>
    <form class="form-crud" id="form-niveles">
        <input type="hidden" name="id_nivel">
        <select name="id_idioma" required></select>
        <select name="nombre_nivel" required>
            <option value="A1">A1</option><option value="A2">A2</option><option value="B1">B1</option>
            <option value="B2">B2</option><option value="C1">C1</option><option value="C2">C2</option>
        </select>
        <button type="submit">Guardar</button>
    </form>
    <input type="text" id="busqueda-niveles" placeholder="Buscar..." class="input-busqueda">`;
    html += generarTabla(lista, ['id_nivel','nombre_idioma','nombre_nivel'], 'niveles');
    document.getElementById('contenido-principal').innerHTML = html;
    await cargarIdiomasEnSelect();
    document.getElementById('form-niveles').addEventListener('submit', e => guardarEntidad(e, 'niveles'));
    document.getElementById('busqueda-niveles').addEventListener('input', e => filtrarTabla('niveles', e.target.value));
}

async function renderizarGrupos(lista) { // Hacemos esta función asíncrona
    window.gruposData = lista;
    let html = `<h2>📚 Grupos</h2>
    <form class="form-crud" id="form-grupos">
        <input type="hidden" name="id_grupo">
        <select name="id_idioma" required></select>
        <select name="id_nivel" required><option value="">Seleccione idioma primero</option></select>
        <select name="id_docente" required></select>
        <input name="horario" placeholder="Horario (Lunes 08:00-10:00)" required>
        <input name="cupo_maximo" type="number" placeholder="Cupo máximo" min="1" value="15">
        <button type="submit">Guardar</button>
    </form>
    <input type="text" id="busqueda-grupos" placeholder="Buscar..." class="input-busqueda">`;
    html += generarTabla(lista, ['id_grupo','nombre_idioma','nombre_nivel','docente','horario','cupo_maximo','estado'], 'grupos');
    document.getElementById('contenido-principal').innerHTML = html;
    await cargarIdiomasEnSelect(); // Esperamos a que los idiomas se carguen
    await cargarDocentesEnSelect(); // Esperamos a que los docentes se carguen
    const selectIdioma = document.querySelector('#form-grupos [name="id_idioma"]');
    if (selectIdioma) selectIdioma.addEventListener('change', cargarNivelesPorIdioma);
    document.getElementById('form-grupos').addEventListener('submit', e => guardarEntidad(e, 'grupos'));
    document.getElementById('busqueda-grupos').addEventListener('input', e => filtrarTabla('grupos', e.target.value));
}

async function renderizarInscripciones(lista) {
    window.inscripcionesData = lista;
    let html = `<h2>📝 Inscripciones</h2>
    <form class="form-crud" id="form-inscripciones">
        <select name="id_estudiante" required></select>
        <select name="id_grupo" required></select>
        <button type="submit">Inscribir</button>
    </form>
    <input type="text" id="busqueda-inscripciones" placeholder="Buscar..." class="input-busqueda">`;
    html += generarTabla(lista, ['id_inscripcion','estudiante','nombre_idioma','nombre_nivel','horario','fecha_inscripcion','estado'], 'inscripciones');
    document.getElementById('contenido-principal').innerHTML = html;
    await cargarEstudiantesEnSelect();
    await cargarGruposEnSelect();
    document.getElementById('form-inscripciones').addEventListener('submit', e => guardarEntidad(e, 'inscripciones'));
    document.getElementById('busqueda-inscripciones').addEventListener('input', e => filtrarTabla('inscripciones', e.target.value));
}

async function renderizarEvaluaciones(lista) {
    window.evaluacionesData = lista;
    let html = `<h2>📋 Evaluaciones</h2>
    <form class="form-crud" id="form-evaluaciones">
        <input type="hidden" name="id_evaluacion">
        <select name="id_inscripcion" required></select>
        <select name="modulo" required>
            <option value="Gramática">Gramática</option><option value="Conversación">Conversación</option>
            <option value="Escritura">Escritura</option><option value="Comprensión auditiva">Comprensión auditiva</option>
        </select>
        <input name="nota" type="number" step="0.1" min="0" max="5" placeholder="Nota (0-5)" required>
        <input name="fecha_evaluacion" type="date" required>
        <button type="submit">Guardar</button>
    </form>
    <input type="text" id="busqueda-evaluaciones" placeholder="Buscar..." class="input-busqueda">`;
    html += generarTabla(lista, ['id_evaluacion','estudiante','modulo','nota','fecha_evaluacion'], 'evaluaciones');
    document.getElementById('contenido-principal').innerHTML = html;
    await cargarInscripcionesEnSelect();
    document.getElementById('form-evaluaciones').addEventListener('submit', e => guardarEntidad(e, 'evaluaciones'));
    document.getElementById('busqueda-evaluaciones').addEventListener('input', e => filtrarTabla('evaluaciones', e.target.value));
}

async function renderizarCertificados(lista) {
    window.certificadosData = lista;
    let html = `<h2>🏅 Certificados</h2>
    <form class="form-crud" id="form-certificados">
        <select name="id_estudiante" required></select>
        <select name="id_nivel" required></select>
        <button type="submit">Emitir Certificado</button>
    </form>
    <input type="text" id="busqueda-certificados" placeholder="Buscar..." class="input-busqueda">`;
    html += generarTabla(lista, ['id_certificado','estudiante','nombre_nivel','nombre_idioma','fecha_emision'], 'certificados');
    document.getElementById('contenido-principal').innerHTML = html;
    await cargarEstudiantesEnSelect();
    await cargarNivelesEnSelect();
    document.getElementById('form-certificados').addEventListener('submit', emitirCertificado);
    document.getElementById('busqueda-certificados').addEventListener('input', e => filtrarTabla('certificados', e.target.value));
}

// ---------- CALENDARIO Y ASISTENCIA ----------
async function renderizarCalendario() {
    document.getElementById('contenido-principal').innerHTML = `
        <h2>📅 Calendario de Clases</h2>
        <div class="calendario-selector">
            <label>Grupo:</label>
            <select id="select-grupo-calendario"><option value="">Cargando...</option></select>
        </div>
        <div id="calendario-mensual"></div>
        <div id="asistencia-detalle">
            <h3>Asistencia - <span id="fecha-asistencia"></span></h3>
            <table id="tabla-asistencia"><thead><tr><th>Estudiante</th><th>Presente</th></tr></thead><tbody></tbody></table>
        </div>`;
    await cargarGruposEnSelectCalendario();
    document.getElementById('select-grupo-calendario').addEventListener('change', async (e) => {
        grupoSeleccionado = e.target.value;
        if (grupoSeleccionado) {
            await generarCalendarioMensual();
            await cargarAsistencia();
        }
    });
    // Auto-seleccionar primer grupo
    const select = document.getElementById('select-grupo-calendario');
    if (select.options.length > 1) {
        select.selectedIndex = 1;
        select.dispatchEvent(new Event('change'));
    } else {
        generarCalendarioMensual(); // calendario genérico
    }
}

async function cargarGruposEnSelectCalendario() {
    try {
        const resp = await fetch(API + 'grupos.php?accion=listar');
        const grupos = await resp.json();
        const select = document.getElementById('select-grupo-calendario');
        select.innerHTML = '<option value="">Seleccione grupo</option>';
        grupos.forEach(g => select.innerHTML += `<option value="${g.id_grupo}">${g.nombre_idioma} ${g.nombre_nivel} - ${g.horario}</option>`);
    } catch (e) {
        document.getElementById('select-grupo-calendario').innerHTML = '<option value="">Error al cargar grupos</option>';
    }
}

async function generarCalendarioMensual() {
    const hoy = new Date();
    const mes = hoy.getMonth();
    const año = hoy.getFullYear();
    const diasMes = new Date(año, mes+1, 0).getDate();
    const primerDia = new Date(año, mes, 1).getDay();
    const nombresMeses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    let html = `<h4>${nombresMeses[mes]} ${año}</h4><div class="calendario-grid">`;
    ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'].forEach(d => html += `<div class="calendario-cabecera">${d}</div>`);
    for (let i=0; i<primerDia; i++) html += '<div class="calendario-dia vacio"></div>';
    for (let dia=1; dia<=diasMes; dia++) {
        const fecha = `${año}-${String(mes+1).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
        const esHoy = fecha === new Date().toISOString().split('T')[0] ? 'hoy' : '';
        const diaSemana = new Date(año, mes, dia).getDay();
        const horario = document.getElementById('select-grupo-calendario')?.selectedOptions[0]?.text.split(' - ')[1] || '';
        const tieneClase = verificarDiaClase(horario, diaSemana);
        html += `<div class="calendario-dia ${esHoy} ${tieneClase ? 'clase' : ''}" data-fecha="${fecha}">${dia}</div>`;
    }
    html += '</div>';
    document.getElementById('calendario-mensual').innerHTML = html;
    document.querySelectorAll('.calendario-dia.clase').forEach(div => {
        div.addEventListener('click', (e) => {
            fechaSeleccionada = e.currentTarget.dataset.fecha;
            document.getElementById('fecha-asistencia').textContent = fechaSeleccionada;
            cargarAsistencia();
            document.querySelectorAll('.calendario-dia.seleccionado').forEach(d => d.classList.remove('seleccionado'));
            e.currentTarget.classList.add('seleccionado');
        });
    });
}

function verificarDiaClase(horario, diaSemana) {
    if (!horario) return false;
    const diasMap = { 'Lunes':1, 'Martes':2, 'Miércoles':3, 'Jueves':4, 'Viernes':5, 'Sábado':6 };
    const dias = horario.split(' ')[0].split('-');
    return dias.some(d => diasMap[d] === diaSemana);
}

async function cargarAsistencia() {
    if (!grupoSeleccionado) return;
    try {
        const resp = await fetch(`${API}asistencias.php?accion=listar&id_grupo=${grupoSeleccionado}&fecha=${fechaSeleccionada}`);
        const datos = await resp.json();
        const tbody = document.querySelector('#tabla-asistencia tbody');
        tbody.innerHTML = '';
        datos.forEach(reg => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${reg.estudiante}</td>
                <td><input type="checkbox" ${reg.presente==1?'checked':''} data-id-inscripcion="${reg.id_inscripcion}"></td>`;
            tbody.appendChild(tr);
        });
        document.querySelectorAll('#tabla-asistencia input[type=checkbox]').forEach(chk => {
            chk.addEventListener('change', async (e) => {
                const idInscripcion = e.target.dataset.idInscripcion;
                const presente = e.target.checked ? 1 : 0;
                await fetch(API + 'asistencias.php', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ id_inscripcion: idInscripcion, fecha: fechaSeleccionada, presente, csrf_token: csrfToken })
                });
                mostrarToast('Asistencia actualizada', 'success');
            });
        });
    } catch (e) {
        console.error('Error al cargar asistencia:', e);
    }
}

// ---------- FUNCIONES CRUD Y AUXILIARES ----------
function generarTabla(registros, columnas, modulo) {
    if (!registros.length) return '<div style="padding: 2rem; text-align: center; color: var(--text-muted);">No hay datos registrados aún.</div>';
    let tabla = '<div class="table-responsive"><table><thead><tr>';
    columnas.forEach(col => tabla += `<th>${col.replace(/_/g, ' ')}</th>`);
    tabla += '<th>Acciones</th></tr></thead><tbody>';
    registros.forEach(reg => {
        tabla += '<tr>';
        columnas.forEach(col => {
            let valor = reg[col] ?? '';
            // Adaptación visual para la columna estado sin romper la lógica
            if (col === 'estado') {
                valor = reg[col] == 1 ? '<span class="badge success">Activo</span>' : '<span class="badge danger">Inactivo</span>';
            }
            tabla += `<td>${valor}</td>`;
        });
        tabla += `<td>
            <button class="btn-editar" data-id="${reg[Object.keys(reg)[0]]}" data-modulo="${modulo}">Editar</button>
            <button class="btn-eliminar" data-id="${reg[Object.keys(reg)[0]]}" data-modulo="${modulo}">Eliminar</button>
        </td></tr>`;
    });
    tabla += '</tbody></table></div>';
    return tabla;
}

async function guardarEntidad(e, modulo) {
    e.preventDefault();
    const form = e.target;
    const datos = Object.fromEntries(new FormData(form));
    datos.csrf_token = csrfToken;
    if ((modulo==='estudiantes'||modulo==='docentes') && datos.correo && !datos.correo.includes('@')) {
        mostrarToast('Correo inválido', 'error'); return;
    }
    if ((modulo==='estudiantes'||modulo==='docentes') && datos.documento && !/^\d+$/.test(datos.documento)) {
        mostrarToast('El documento debe contener solo números', 'error'); return;
    }
    try {
        const resp = await fetch(`${API}${modulo}.php`, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(datos) });
        
        if (!resp.ok) {
            const errorData = await resp.json().catch(() => ({ error: 'Error interno del servidor' }));
            throw new Error(errorData.error || `Error ${resp.status}`);
        }

        const data = await resp.json();
        if (data.ok) { mostrarToast(data.mensaje||'Guardado', 'success'); cargarModulo(modulo); }
        else mostrarToast('Error: '+(data.error||'Desconocido'), 'error');
    } catch (e) { console.error(e); mostrarToast(e.message === 'Failed to fetch' ? 'Error de conexión. ¿Servidor activo?' : e.message, 'error'); }
}

function eliminarEntidad(modulo, id) {
    mostrarModal('¿Eliminar este registro?', async () => {
        const resp = await fetch(`${API}${modulo}.php?id=${id}`, { method: 'DELETE' });
        const data = await resp.json();
        if (data.ok) cargarModulo(modulo);
        else mostrarToast('Error: '+(data.error||'No se pudo eliminar'), 'error');
    });
}

async function cargarDatosEnFormulario(modulo, id) {
    const datos = window[modulo + 'Data'] || [];
    const registro = datos.find(r => r[Object.keys(r)[0]] == id);
    if (!registro) return;
    const form = document.getElementById('form-' + modulo);
    if (!form) return;

    // Manejo especial para el módulo 'grupos' debido a selectores dependientes
    if (modulo === 'grupos') {
        // Primero, establecer el idioma y esperar a que los niveles se filtren
        const selectIdioma = form.querySelector('[name="id_idioma"]');
        if (selectIdioma && registro.id_idioma) {
            selectIdioma.value = registro.id_idioma;
            await cargarNivelesPorIdioma(); // Esperar a que los niveles se carguen
        }
        // Luego, establecer todos los demás campos, incluyendo el nivel
        for (const [campo, valor] of Object.entries(registro)) {
            const input = form.querySelector(`[name="${campo}"]`);
            if (input) {
                input.value = valor ?? '';
            }
        }
    }
    else {
        // Lógica original para otros módulos
        for (const [campo, valor] of Object.entries(registro)) {
            const input = form.querySelector(`[name="${campo}"]`);
            if (input) { 
                input.value = valor ?? '';
                if (input.tagName === 'SELECT') {
                    // Disparar evento change para cargar selectores dependientes
                    input.dispatchEvent(new Event('change'));
                }
            }
        }
    }
    form.scrollIntoView({ behavior: 'smooth' });
}

function filtrarTabla(modulo, texto) {
    const tabla = document.querySelector(`#contenido-principal table`);
    if (!tabla) return;
    const filas = tabla.querySelectorAll('tbody tr');
    const termino = texto.toLowerCase();
    filas.forEach(fila => {
        const visible = Array.from(fila.cells).some(cell => cell.textContent.toLowerCase().includes(termino));
        fila.style.display = visible ? '' : 'none';
    });
}

function mostrarModal(mensaje, callback) {
    document.getElementById('modal-mensaje').textContent = mensaje;
    document.getElementById('modal-confirm').classList.add('activo');
    callbackEliminar = callback;
}

function mostrarToast(mensaje, tipo = 'info') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${tipo}`;
    toast.textContent = mensaje;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

async function emitirCertificado(e) {
    e.preventDefault();
    const form = new FormData(e.target);
    const datos = { accion: 'emitir', id_estudiante: form.get('id_estudiante'), id_nivel: form.get('id_nivel'), csrf_token: csrfToken };
    const resp = await fetch(API + 'certificados.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(datos) });
    const data = await resp.json();
    if (data.ok) { mostrarToast(data.mensaje, 'success'); cargarModulo('certificados'); }
    else mostrarToast('Error: '+(data.error||'No emitido'), 'error');
}

// ---------- CARGA DE OPCIONES PARA SELECTS ----------
async function cargarIdiomasEnSelect() {
    const resp = await fetch(API + 'idiomas.php?accion=listar');
    const idiomas = await resp.json();
    document.querySelectorAll('[name="id_idioma"]').forEach(select => {
        select.innerHTML = '<option value="">Seleccione idioma</option>';
        idiomas.forEach(i => select.innerHTML += `<option value="${i.id_idioma}">${i.nombre_idioma}</option>`);
    });
}
async function cargarDocentesEnSelect() {
    const resp = await fetch(API + 'docentes.php?accion=listar');
    const docentes = await resp.json();
    const select = document.querySelector('#form-grupos [name="id_docente"]');
    if (select) {
        select.innerHTML = '<option value="">Seleccione docente</option>';
        docentes.forEach(d => select.innerHTML += `<option value="${d.id_docente}">${d.nombres} ${d.apellidos}</option>`);
    }
}
async function cargarNivelesPorIdioma() {
    const selectIdioma = document.querySelector('#form-grupos [name="id_idioma"]');
    const selectNivel = document.querySelector('#form-grupos [name="id_nivel"]');
    if (!selectIdioma || !selectNivel) return;
    
    const idIdioma = selectIdioma.value;
    if (!idIdioma) { selectNivel.innerHTML = '<option value="">Seleccione idioma primero</option>'; return; }

    const resp = await fetch(API + 'niveles.php?accion=listar');
    const niveles = await resp.json();
    selectNivel.innerHTML = '<option value="">Seleccione nivel</option>';
    niveles.filter(n => n.id_idioma == idIdioma).forEach(n => selectNivel.innerHTML += `<option value="${n.id_nivel}">${n.nombre_nivel}</option>`);
}
async function cargarEstudiantesEnSelect() {
    const resp = await fetch(API + 'estudiantes.php?accion=listar');
    const lista = await resp.json();
    document.querySelectorAll('[name="id_estudiante"]').forEach(select => {
        if (select) {
            select.innerHTML = '<option value="">Seleccione estudiante</option>';
            lista.forEach(e => select.innerHTML += `<option value="${e.id_estudiante}">${e.nombres} ${e.apellidos}</option>`);
        }
    });
}
async function cargarGruposEnSelect() {
    const resp = await fetch(API + 'grupos.php?accion=listar');
    const grupos = await resp.json();
    document.querySelectorAll('[name="id_grupo"]').forEach(select => {
        if (select) {
            select.innerHTML = '<option value="">Seleccione grupo</option>';
            grupos.forEach(g => select.innerHTML += `<option value="${g.id_grupo}">${g.nombre_idioma} ${g.nombre_nivel} - ${g.horario}</option>`);
        }
    });
}
async function cargarInscripcionesEnSelect() {
    const resp = await fetch(API + 'inscripciones.php?accion=listar');
    const insc = await resp.json();
    document.querySelectorAll('[name="id_inscripcion"]').forEach(select => {
        if (select) {
            select.innerHTML = '<option value="">Seleccione inscripción</option>';
            insc.forEach(i => select.innerHTML += `<option value="${i.id_inscripcion}">${i.estudiante} - ${i.nombre_idioma} ${i.nombre_nivel}</option>`);
        }
    });
}
async function cargarNivelesEnSelect() {
    const resp = await fetch(API + 'niveles.php?accion=listar');
    const niveles = await resp.json();
    document.querySelectorAll('[name="id_nivel"]').forEach(select => {
        if (select) {
            select.innerHTML = '<option value="">Seleccione nivel</option>';
            niveles.forEach(n => select.innerHTML += `<option value="${n.id_nivel}">${n.nombre_idioma} - ${n.nombre_nivel}</option>`);
        }
    });
}

// ---------- EVENTOS DELEGADOS ----------
document.getElementById('contenido-principal').addEventListener('click', async (e) => {
    if (e.target.classList.contains('btn-eliminar')) {
        eliminarEntidad(e.target.dataset.modulo, e.target.dataset.id);
    } else if (e.target.classList.contains('btn-editar')) {
        await cargarDatosEnFormulario(e.target.dataset.modulo, e.target.dataset.id);
    }
});