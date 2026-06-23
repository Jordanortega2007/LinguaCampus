// ================================================================
//  LinguaCampus — Módulo de Mejoras (mejoras.js)
//  Carga DESPUÉS de app.js. No modifica PHP ni la base de datos.
//  Sobreescribe funciones globales de forma no destructiva.
// ================================================================

/* ---- ESTADO GLOBAL DE MEJORAS ---- */
const MEJORAS = {
    moduloActual: null,
    paginaActual: 1,
    registrosPorPagina: 10,
};

/* ---- CAPTURAR REFERENCIAS ANTES DE SOBRESCRIBIR ---- */
const _cargarModuloOriginal = window.cargarModulo;
const _filtrarTablaOriginal  = window.filtrarTabla;

/* ================================================================
   1. PATCH: cargarModulo — animación + soporte 'inicio' + mejoras
   ================================================================ */
window.cargarModulo = async function(modulo) {
    MEJORAS.moduloActual = modulo;
    MEJORAS.paginaActual = 1;

    const cont = document.getElementById('contenido-principal');
    // Animación de salida rápida
    cont.classList.remove('modulo-fade-in');
    void cont.offsetWidth; // forzar reflow

    if (modulo === 'inicio') {
        actualizarMenuActivo('inicio');
        await renderizarDashboard();
    } else {
        await _cargarModuloOriginal(modulo);
        // Enganchar mejoras 100ms después del render
        setTimeout(() => aplicarMejorasModulo(modulo), 100);
    }

    // Animación de entrada
    cont.classList.add('modulo-fade-in');
};

/* ================================================================
   2. PATCH: filtrarTabla — resaltar coincidencias
   ================================================================ */
window.filtrarTabla = function(modulo, texto) {
    // Restaurar celdas con data-original
    document.querySelectorAll('#contenido-principal td[data-orig]').forEach(td => {
        td.innerHTML = td.getAttribute('data-orig');
        td.removeAttribute('data-orig');
    });

    _filtrarTablaOriginal(modulo, texto);
    MEJORAS.paginaActual = 1;

    if (texto.trim().length >= 2) {
        const escaped = texto.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${escaped})`, 'gi');

        document.querySelectorAll('#contenido-principal tbody tr').forEach(fila => {
            if (fila.style.display === 'none') return;
            Array.from(fila.cells).forEach(td => {
                // Evitar celdas con badges o botones
                if (td.querySelector('.badge, button, input')) return;
                const orig = td.textContent;
                if (regex.test(orig)) {
                    td.setAttribute('data-orig', td.innerHTML);
                    td.innerHTML = orig.replace(regex, '<mark>$1</mark>');
                }
                regex.lastIndex = 0;
            });
        });
    }

    aplicarPaginacion(modulo);
    actualizarContador();
};

/* ================================================================
   HELPERS
   ================================================================ */
function actualizarMenuActivo(modulo) {
    document.querySelectorAll('#menu-lateral a').forEach(link => {
        link.classList.toggle('activo', link.getAttribute('data-modulo') === modulo);
    });
}

/* ================================================================
   3. DASHBOARD — Pantalla de inicio con estadísticas
   ================================================================ */
async function renderizarDashboard() {
    const contenedor = document.getElementById('contenido-principal');
    contenedor.innerHTML = `
        <h2>🏠 Dashboard</h2>
        <div class="dashboard-grid">
            ${[1,2,3,4,5,6].map(() => `
                <div class="stat-card" style="min-height:110px">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-valor" style="color:var(--text-muted)">—</div>
                    <div class="stat-label" style="color:var(--text-muted)">Cargando...</div>
                </div>`).join('')}
        </div>`;

    try {
        const [estudiantes, docentes, grupos, inscripciones, certificados, evaluaciones] = await Promise.all([
            fetch('api/estudiantes.php?accion=listar').then(r => r.ok ? r.json() : []),
            fetch('api/docentes.php?accion=listar').then(r => r.ok ? r.json() : []),
            fetch('api/grupos.php?accion=listar').then(r => r.ok ? r.json() : []),
            fetch('api/inscripciones.php?accion=listar').then(r => r.ok ? r.json() : []),
            fetch('api/certificados.php?accion=listar').then(r => r.ok ? r.json() : []),
            fetch('api/evaluaciones.php?accion=listar').then(r => r.ok ? r.json() : []),
        ]);

        const est  = Array.isArray(estudiantes) ? estudiantes : [];
        const doc  = Array.isArray(docentes)    ? docentes    : [];
        const grp  = Array.isArray(grupos)      ? grupos      : [];
        const ins  = Array.isArray(inscripciones)? inscripciones: [];
        const cert = Array.isArray(certificados) ? certificados : [];
        const evs  = Array.isArray(evaluaciones) ? evaluaciones : [];

        const estActivos = est.filter(e => e.estado == 1).length;
        const docActivos = doc.filter(d => d.estado == 1).length;
        const grpActivos = grp.filter(g => g.estado == 1).length;
        const insActivas = ins.filter(i => i.estado == 1).length;

        const gruposCasiLlenos = grp.filter(g => {
            const count = ins.filter(i => i.id_grupo == g.id_grupo && i.estado == 1).length;
            return g.cupo_maximo > 0 && (count / g.cupo_maximo) >= 0.8;
        }).length;

        let promedioGeneral = '—';
        if (evs.length > 0) {
            promedioGeneral = (evs.reduce((s, e) => s + parseFloat(e.nota || 0), 0) / evs.length).toFixed(2);
        }

        contenedor.innerHTML = `
            <h2>🏠 Dashboard</h2>
            <div class="dashboard-grid">
                <div class="stat-card" onclick="cargarModulo('estudiantes')" style="cursor:pointer" title="Ver Estudiantes">
                    <div class="stat-icon">👨‍🎓</div>
                    <div class="stat-valor">${estActivos}</div>
                    <div class="stat-label">Estudiantes Activos</div>
                    <div class="stat-sub">de ${est.length} total</div>
                </div>
                <div class="stat-card" onclick="cargarModulo('docentes')" style="cursor:pointer" title="Ver Docentes">
                    <div class="stat-icon">👩‍🏫</div>
                    <div class="stat-valor">${docActivos}</div>
                    <div class="stat-label">Docentes Activos</div>
                    <div class="stat-sub">de ${doc.length} total</div>
                </div>
                <div class="stat-card ${gruposCasiLlenos > 0 ? 'stat-warning' : ''}" onclick="cargarModulo('grupos')" style="cursor:pointer" title="Ver Grupos">
                    <div class="stat-icon">📚</div>
                    <div class="stat-valor">${grpActivos}</div>
                    <div class="stat-label">Grupos Activos</div>
                    <div class="stat-sub">${gruposCasiLlenos > 0 ? `⚠️ ${gruposCasiLlenos} casi lleno(s)` : 'Cupo disponible'}</div>
                </div>
                <div class="stat-card" onclick="cargarModulo('inscripciones')" style="cursor:pointer" title="Ver Inscripciones">
                    <div class="stat-icon">📝</div>
                    <div class="stat-valor">${insActivas}</div>
                    <div class="stat-label">Inscripciones Activas</div>
                    <div class="stat-sub">de ${ins.length} total</div>
                </div>
                <div class="stat-card stat-success" onclick="cargarModulo('certificados')" style="cursor:pointer" title="Ver Certificados">
                    <div class="stat-icon">🏅</div>
                    <div class="stat-valor">${cert.length}</div>
                    <div class="stat-label">Certificados Emitidos</div>
                    <div class="stat-sub">histórico total</div>
                </div>
                <div class="stat-card" onclick="cargarModulo('evaluaciones')" style="cursor:pointer" title="Ver Evaluaciones">
                    <div class="stat-icon">📋</div>
                    <div class="stat-valor">${promedioGeneral}</div>
                    <div class="stat-label">Promedio General</div>
                    <div class="stat-sub">${evs.length} evaluaciones</div>
                </div>
            </div>
            ${evs.length > 0  ? renderizarGraficaEvaluaciones(evs)  : ''}
            ${grp.length > 0  ? renderizarGraficaGrupos(grp, ins)   : ''}
        `;
    } catch (err) {
        console.error('Error dashboard:', err);
        document.getElementById('contenido-principal').innerHTML +=
            `<p class="error">Error al cargar datos del dashboard: ${err.message}</p>`;
    }
}

function renderizarGraficaEvaluaciones(evs) {
    const modulos = ['Gramática', 'Conversación', 'Escritura', 'Comprensión auditiva'];
    const promedios = modulos.map(m => {
        const sub = evs.filter(e => e.modulo === m);
        if (!sub.length) return 0;
        return parseFloat((sub.reduce((s, e) => s + parseFloat(e.nota || 0), 0) / sub.length).toFixed(2));
    });
    const maxPct = 5;

    return `
    <div class="grafica-card">
        <h3>📊 Promedio por Módulo de Evaluación</h3>
        <div class="grafica-barras">
            <div class="grafica-linea-ref"><span>5.0 (máx)</span></div>
            <div class="grafica-linea-aprobado"><span>3.5 ✓</span></div>
            ${modulos.map((m, i) => `
                <div class="barra-grupo">
                    <div class="barra-wrap">
                        <span class="barra-valor">${promedios[i]}</span>
                        <div class="barra" style="height:${(promedios[i]/maxPct)*100}%" title="${m}: ${promedios[i]}"></div>
                    </div>
                    <div class="barra-label">${m}</div>
                </div>`).join('')}
        </div>
    </div>`;
}

function renderizarGraficaGrupos(grupos, inscripciones) {
    const activos = grupos.filter(g => g.estado == 1).slice(0, 8);
    if (!activos.length) return '';
    return `
    <div class="grafica-card">
        <h3>📚 Ocupación de Grupos Activos</h3>
        <div class="grupos-ocupacion">
            ${activos.map(g => {
                const inscritos = inscripciones.filter(i => i.id_grupo == g.id_grupo && i.estado == 1).length;
                const pct = g.cupo_maximo > 0 ? Math.round((inscritos / g.cupo_maximo) * 100) : 0;
                const color = pct >= 90 ? 'var(--danger)' : pct >= 70 ? 'var(--warning)' : 'var(--success)';
                return `
                <div class="ocupacion-row">
                    <div class="ocupacion-label" title="${g.nombre_idioma} ${g.nombre_nivel} — ${g.horario || ''}">
                        ${g.nombre_idioma} ${g.nombre_nivel}
                    </div>
                    <div class="ocupacion-barra-wrap">
                        <div class="ocupacion-barra" style="width:${Math.min(pct,100)}%;background:${color}"></div>
                    </div>
                    <div class="ocupacion-num">${inscritos}/${g.cupo_maximo} <span style="font-size:0.7rem;color:var(--text-muted)">(${pct}%)</span></div>
                </div>`;
            }).join('')}
        </div>
    </div>`;
}

/* ================================================================
   4. APLICAR MEJORAS POST-RENDER a un módulo
   ================================================================ */
function aplicarMejorasModulo(modulo) {
    const contenedor = document.getElementById('contenido-principal');
    const tabla = contenedor.querySelector('table');
    if (!tabla) return;

    // Evitar duplicados si se llama dos veces
    if (document.getElementById('mejoras-toolbar')) {
        document.getElementById('mejoras-toolbar').remove();
    }

    // --- Toolbar ---
    const toolbar = document.createElement('div');
    toolbar.className = 'mejoras-toolbar';
    toolbar.id = 'mejoras-toolbar';
    toolbar.innerHTML = `
        <div class="toolbar-left">
            <span class="toolbar-count" id="toolbar-count"></span>
        </div>
        <div class="toolbar-right">
            <button class="btn-toolbar" id="btn-export-csv" title="Exportar CSV (Ctrl+E)">📥 CSV</button>
            <button class="btn-toolbar" id="btn-imprimir" title="Imprimir módulo">🖨️ Imprimir</button>
        </div>`;

    const tableWrap = contenedor.querySelector('.table-responsive');
    if (tableWrap) tableWrap.parentNode.insertBefore(toolbar, tableWrap);

    document.getElementById('btn-export-csv')?.addEventListener('click', () => exportarCSV(modulo));
    document.getElementById('btn-imprimir')?.addEventListener('click', () => imprimirModulo(modulo));

    // Actualizar contador inicial
    actualizarContador();

    // --- Ordenar columnas ---
    hacerOrdenable(tabla, modulo);

    // --- Paginación ---
    aplicarPaginacion(modulo);

    // --- Toggle de estado ---
    agregarToggleEstado(modulo);

    // --- Perfil de estudiante si aplica ---
    if (modulo === 'estudiantes') {
        agregarEnlacePerfilEstudiante();
    }
}

/* ================================================================
   5. CONTADOR de registros visibles
   ================================================================ */
function actualizarContador() {
    const filas = document.querySelectorAll('#contenido-principal tbody tr');
    const visibles = Array.from(filas).filter(f => f.style.display !== 'none').length;
    const total    = filas.length;
    const el       = document.getElementById('toolbar-count');
    if (el) el.textContent = visibles === total
        ? `${total} registro(s)`
        : `${visibles} de ${total} registro(s)`;
}

/* ================================================================
   6. ORDENAR COLUMNAS
   ================================================================ */
function hacerOrdenable(tabla, modulo) {
    tabla.querySelectorAll('thead th').forEach((th, idx) => {
        if (th.textContent.trim() === 'Acciones') return;
        th.style.cursor = 'pointer';
        th.style.userSelect = 'none';
        const textoOriginal = th.textContent.trim();
        th.innerHTML = `<span>${textoOriginal}</span><span class="sort-icon">⇅</span>`;
        th.addEventListener('click', () => ordenarPorColumna(tabla, idx, th, modulo));
    });
}

function ordenarPorColumna(tabla, idx, thEl, modulo) {
    const tbody  = tabla.querySelector('tbody');
    const filas  = Array.from(tbody.querySelectorAll('tr'));
    const curDir = thEl.getAttribute('data-dir') || '';
    const newDir = curDir === 'asc' ? 'desc' : 'asc';

    // Reset todas las cabeceras
    tabla.querySelectorAll('th').forEach(th => {
        th.setAttribute('data-dir', '');
        const icon = th.querySelector('.sort-icon');
        if (icon) icon.textContent = '⇅';
    });

    thEl.setAttribute('data-dir', newDir);
    const icon = thEl.querySelector('.sort-icon');
    if (icon) icon.textContent = newDir === 'asc' ? '↑' : '↓';

    filas.sort((a, b) => {
        const aT = a.cells[idx]?.textContent.trim() || '';
        const bT = b.cells[idx]?.textContent.trim() || '';
        const aN = parseFloat(aT), bN = parseFloat(bT);
        if (!isNaN(aN) && !isNaN(bN)) return newDir === 'asc' ? aN - bN : bN - aN;
        return newDir === 'asc' ? aT.localeCompare(bT, 'es') : bT.localeCompare(aT, 'es');
    });

    filas.forEach(f => tbody.appendChild(f));
    MEJORAS.paginaActual = 1;
    aplicarPaginacion(modulo);
    actualizarContador();
}

/* ================================================================
   7. PAGINACIÓN
   ================================================================ */
function aplicarPaginacion(modulo) {
    const rpp   = MEJORAS.registrosPorPagina;
    const tbody = document.querySelector('#contenido-principal tbody');
    if (!tbody) return;

    const todas = Array.from(tbody.querySelectorAll('tr'));
    const visibles = todas.filter(f => f.style.display !== 'none');

    // Remover paginación anterior
    document.getElementById('paginacion-ctrl')?.remove();

    if (visibles.length <= rpp) {
        // Si no supera el límite, mostrar todo
        visibles.forEach(f => f.style.display = '');
        actualizarContador();
        return;
    }

    const totalPags = Math.ceil(visibles.length / rpp);
    const pag = Math.min(MEJORAS.paginaActual, totalPags);
    MEJORAS.paginaActual = pag;

    const ini = (pag - 1) * rpp;
    const fin = ini + rpp;

    visibles.forEach((f, i) => {
        f.style.display = (i >= ini && i < fin) ? '' : 'none';
    });

    // Construir botones
    let btns = `<button class="btn-pag" id="pag-prev" ${pag === 1 ? 'disabled' : ''}>← Ant</button>`;
    for (let p = 1; p <= totalPags; p++) {
        if (p === 1 || p === totalPags || Math.abs(p - pag) <= 1) {
            btns += `<button class="btn-pag ${p === pag ? 'activo' : ''}" data-pag="${p}">${p}</button>`;
        } else if (Math.abs(p - pag) === 2) {
            btns += `<span class="pag-dots">…</span>`;
        }
    }
    btns += `<button class="btn-pag" id="pag-next" ${pag === totalPags ? 'disabled' : ''}>Sig →</button>`;
    btns += `<span class="pag-info">Pág. ${pag}/${totalPags}</span>`;

    const paginacion = document.createElement('div');
    paginacion.id = 'paginacion-ctrl';
    paginacion.className = 'paginacion';
    paginacion.innerHTML = btns;

    const tableWrap = document.querySelector('#contenido-principal .table-responsive');
    if (tableWrap) tableWrap.after(paginacion);

    paginacion.querySelectorAll('[data-pag]').forEach(btn => {
        btn.addEventListener('click', () => {
            MEJORAS.paginaActual = parseInt(btn.dataset.pag);
            aplicarPaginacion(modulo);
            actualizarContador();
        });
    });
    document.getElementById('pag-prev')?.addEventListener('click', () => {
        if (MEJORAS.paginaActual > 1) { MEJORAS.paginaActual--; aplicarPaginacion(modulo); actualizarContador(); }
    });
    document.getElementById('pag-next')?.addEventListener('click', () => {
        if (MEJORAS.paginaActual < totalPags) { MEJORAS.paginaActual++; aplicarPaginacion(modulo); actualizarContador(); }
    });

    actualizarContador();
}

/* ================================================================
   8. TOGGLE DE ESTADO RÁPIDO
   ================================================================ */
const MODULOS_TOGGLE_ESTADO = {
    estudiantes:  'id_estudiante',
    docentes:     'id_docente',
    grupos:       'id_grupo',
    idiomas:      'id_idioma',
    niveles:      'id_nivel',
};

function agregarToggleEstado(modulo) {
    if (!MODULOS_TOGGLE_ESTADO[modulo]) return;
    const pk = MODULOS_TOGGLE_ESTADO[modulo];

    document.querySelectorAll('#contenido-principal .badge').forEach(badge => {
        badge.style.cursor = 'pointer';
        badge.title = 'Clic para cambiar estado';
        badge.addEventListener('click', async (e) => {
            e.stopPropagation();
            const fila = badge.closest('tr');
            const btnEditar = fila?.querySelector('.btn-editar');
            if (!btnEditar) return;

            const id     = btnEditar.dataset.id;
            const datos  = window[modulo + 'Data'] || [];
            const reg    = datos.find(r => String(r[pk]) === String(id));
            if (!reg) return;

            const nuevoEstado = reg.estado == 1 ? 0 : 1;
            const payload     = { ...reg, estado: nuevoEstado, csrf_token: csrfToken };

            badge.textContent = '⏳';
            try {
                const resp = await fetch(`api/${modulo}.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await resp.json();
                if (data.ok) {
                    reg.estado = nuevoEstado;
                    badge.className = `badge ${nuevoEstado == 1 ? 'success' : 'danger'}`;
                    badge.textContent = nuevoEstado == 1 ? 'Activo' : 'Inactivo';
                    mostrarToast(`Estado cambiado a ${nuevoEstado == 1 ? 'Activo' : 'Inactivo'}`, 'success');
                } else {
                    badge.className = `badge ${reg.estado == 1 ? 'success' : 'danger'}`;
                    badge.textContent = reg.estado == 1 ? 'Activo' : 'Inactivo';
                    mostrarToast('Error: ' + (data.error || 'No se pudo cambiar'), 'error');
                }
            } catch (err) {
                badge.className = `badge ${reg.estado == 1 ? 'success' : 'danger'}`;
                badge.textContent = reg.estado == 1 ? 'Activo' : 'Inactivo';
                mostrarToast('Error de conexión', 'error');
            }
        });
    });
}

/* ================================================================
   9. EXPORTAR CSV
   ================================================================ */
function exportarCSV(modulo) {
    const tabla = document.querySelector('#contenido-principal table');
    if (!tabla) return;

    const headers = Array.from(tabla.querySelectorAll('thead th'))
        .map(th => (th.querySelector('span') ? th.querySelector('span').textContent : th.textContent).trim())
        .filter(h => h !== 'Acciones' && h !== '');

    // Mostrar todas las filas para incluirlas en el export
    const filas = Array.from(tabla.querySelectorAll('tbody tr'));
    const estadosPrev = filas.map(f => f.style.display);
    filas.forEach(f => f.style.display = '');

    const rows = filas.map(tr =>
        Array.from(tr.cells).slice(0, headers.length)
            .map(td => `"${td.textContent.trim().replace(/"/g, '""')}"`)
            .join(',')
    );

    filas.forEach((f, i) => f.style.display = estadosPrev[i]);

    const csv  = [headers.join(','), ...rows].join('\r\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `linguacampus_${modulo}_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    mostrarToast('✅ CSV exportado correctamente', 'success');
}

/* ================================================================
   10. IMPRIMIR MÓDULO
   ================================================================ */
function imprimirModulo(modulo) {
    const tabla = document.querySelector('#contenido-principal table');
    if (!tabla) return;

    const titulo = document.querySelector('#contenido-principal h2')?.textContent || modulo;
    const fecha  = new Date().toLocaleDateString('es-CO', { year:'numeric', month:'long', day:'numeric' });
    const usuario = document.getElementById('usuario-activo')?.textContent || '';

    // Mostrar todas las filas temporalmente
    const filas = tabla.querySelectorAll('tbody tr');
    const prevs = Array.from(filas).map(f => f.style.display);
    filas.forEach(f => f.style.display = '');

    // Clonar tabla y quitar columna de acciones
    const tablaClone = tabla.cloneNode(true);
    tablaClone.querySelectorAll('th:last-child, td:last-child').forEach(el => el.remove());
    tablaClone.querySelectorAll('.sort-icon').forEach(el => el.remove());

    const win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(`<!DOCTYPE html><html lang="es"><head>
        <meta charset="UTF-8">
        <title>LinguaCampus — ${titulo}</title>
        <style>
            *{box-sizing:border-box;margin:0;padding:0}
            body{font-family:'Segoe UI',Arial,sans-serif;padding:2cm 2.5cm;color:#1e293b;font-size:10pt}
            .ph{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;padding-bottom:0.75rem;border-bottom:2px solid #6366f1}
            .logo{font-size:16pt;font-weight:800;color:#6366f1}
            .meta{text-align:right;font-size:8pt;color:#64748b;line-height:1.6}
            h1{font-size:13pt;font-weight:700;margin-bottom:1rem;color:#1e293b}
            table{width:100%;border-collapse:collapse;font-size:9pt}
            th{background:#f1f5f9;padding:6px 8px;text-align:left;border:1px solid #e2e8f0;font-weight:700;color:#64748b;text-transform:uppercase;font-size:7.5pt;letter-spacing:.04em}
            td{padding:5px 8px;border:1px solid #e2e8f0;vertical-align:middle}
            tr:nth-child(even){background:#f8fafc}
            .footer{margin-top:1rem;font-size:7.5pt;color:#94a3b8;text-align:center}
            @page{margin:1.5cm}
        </style></head><body>
        <div class="ph">
            <div class="logo">🏫 LinguaCampus</div>
            <div class="meta"><div>${fecha}</div><div>${usuario}</div></div>
        </div>
        <h1>${titulo}</h1>
        ${tablaClone.outerHTML}
        <div class="footer">Reporte generado automáticamente por LinguaCampus</div>
        <script>window.onload=()=>{window.print();window.close();}<\/script>
        </body></html>`);
    win.document.close();

    filas.forEach((f, i) => f.style.display = prevs[i]);
}

/* ================================================================
   11. PERFIL DE ESTUDIANTE — Drawer lateral
   ================================================================ */
function agregarEnlacePerfilEstudiante() {
    document.querySelectorAll('#contenido-principal tbody tr').forEach(fila => {
        const btnEditar = fila.querySelector('.btn-editar');
        if (!btnEditar) return;
        const id = btnEditar.dataset.id;

        // Hacer que el nombre del estudiante (primera columna de texto) sea clicable
        const celdaNombre = fila.cells[2]; // nombres
        if (!celdaNombre || celdaNombre.querySelector('.link-perfil')) return;
        const textoNombre = celdaNombre.textContent.trim();
        celdaNombre.innerHTML = `<span class="link-perfil" style="color:var(--primary);cursor:pointer;font-weight:600;text-decoration:underline;text-underline-offset:2px" title="Ver perfil">${textoNombre}</span>`;
        celdaNombre.querySelector('.link-perfil').addEventListener('click', () => abrirPerfilEstudiante(id));
    });
}

let drawerInyectado = false;
function inyectarDrawer() {
    if (drawerInyectado) return;
    drawerInyectado = true;
    const overlay = document.createElement('div');
    overlay.id    = 'lc-overlay';
    overlay.className = 'lc-overlay';
    overlay.addEventListener('click', cerrarDrawer);

    const drawer = document.createElement('div');
    drawer.id    = 'lc-drawer';
    drawer.className = 'lc-drawer';
    drawer.innerHTML = `
        <div class="drawer-header">
            <div>
                <div class="drawer-titulo" id="drawer-titulo">Perfil del Estudiante</div>
                <div class="drawer-subtitulo" id="drawer-subtitulo"></div>
            </div>
            <button class="drawer-cerrar" id="btn-drawer-cerrar" onclick="cerrarDrawer()">✕</button>
        </div>
        <div class="drawer-body" id="drawer-body"><p style="color:var(--text-muted);text-align:center;padding:2rem">Cargando...</p></div>`;

    document.body.appendChild(overlay);
    document.body.appendChild(drawer);
}

async function abrirPerfilEstudiante(id) {
    inyectarDrawer();
    document.getElementById('lc-overlay').classList.add('activo');
    document.getElementById('lc-drawer').classList.add('activo');
    document.getElementById('drawer-body').innerHTML = '<p style="text-align:center;padding:2rem;color:var(--text-muted)">⏳ Cargando perfil...</p>';

    const est = (window.estudiantesData || []).find(e => String(e.id_estudiante) === String(id));
    if (!est) {
        document.getElementById('drawer-body').innerHTML = '<p class="error">Estudiante no encontrado</p>';
        return;
    }

    document.getElementById('drawer-titulo').textContent = `${est.nombres} ${est.apellidos}`;
    document.getElementById('drawer-subtitulo').textContent = `Doc: ${est.documento}`;

    try {
        const [ins, evs, certs] = await Promise.all([
            fetch('api/inscripciones.php?accion=listar').then(r => r.ok ? r.json() : []),
            fetch('api/evaluaciones.php?accion=listar').then(r => r.ok ? r.json() : []),
            fetch('api/certificados.php?accion=listar').then(r => r.ok ? r.json() : []),
        ]);

        const misIns   = Array.isArray(ins)   ? ins.filter(i => String(i.id_estudiante) === String(id)) : [];
        const misCerts = Array.isArray(certs) ? certs.filter(c => String(c.id_estudiante) === String(id)) : [];

        // Promedios por inscripción
        const inscripcionesConPromedio = misIns.map(i => {
            const evsIns = Array.isArray(evs) ? evs.filter(e => String(e.id_inscripcion) === String(i.id_inscripcion)) : [];
            const prom   = evsIns.length ? (evsIns.reduce((s, e) => s + parseFloat(e.nota||0), 0) / evsIns.length).toFixed(2) : null;
            return { ...i, evaluaciones: evsIns, promedio: prom };
        });

        const edad = est.fecha_nacimiento
            ? Math.floor((Date.now() - new Date(est.fecha_nacimiento)) / (365.25*24*3600*1000)) + ' años'
            : 'N/D';

        document.getElementById('drawer-body').innerHTML = `
            <!-- Datos personales -->
            <div class="drawer-seccion">
                <div class="drawer-seccion-titulo">👤 Datos Personales</div>
                ${datoRow('Correo', est.correo || 'N/D')}
                ${datoRow('Teléfono', est.telefono || 'N/D')}
                ${datoRow('Edad', edad)}
                ${datoRow('Estado', `<span class="badge ${est.estado==1?'success':'danger'}">${est.estado==1?'Activo':'Inactivo'}</span>`)}
            </div>

            <!-- Inscripciones -->
            <div class="drawer-seccion">
                <div class="drawer-seccion-titulo">📝 Inscripciones (${misIns.length})</div>
                ${misIns.length === 0 ? '<div class="drawer-empty">Sin inscripciones</div>' :
                    inscripcionesConPromedio.map(i => `
                    <div style="margin-bottom:0.6rem">
                        <div style="font-weight:600;font-size:0.85rem;color:var(--text-main)">${i.nombre_idioma} ${i.nombre_nivel}</div>
                        <div style="font-size:0.78rem;color:var(--text-muted)">${i.horario || ''} · ${i.fecha_inscripcion || ''}</div>
                        ${i.promedio !== null ? `
                        <div class="drawer-nota-barra">
                            <span style="font-size:0.75rem;color:var(--text-muted);min-width:60px">Prom: <b style="color:var(--text-main)">${i.promedio}</b></span>
                            <div class="drawer-nota-barra-track">
                                <div class="drawer-nota-barra-fill" style="width:${(i.promedio/5)*100}%;background:${i.promedio>=3.5?'var(--success)':'var(--danger)'}"></div>
                            </div>
                        </div>` : ''}
                        <div style="margin-top:0.2rem">${i.evaluaciones.map(e =>
                            `<span class="drawer-chip" title="Nota: ${e.nota}">${e.modulo}: <b>${e.nota}</b></span>`
                        ).join('')}</div>
                    </div>`).join('<hr style="border:none;border-top:1px solid rgba(0,0,0,0.06);margin:0.5rem 0">')}
            </div>

            <!-- Certificados -->
            <div class="drawer-seccion">
                <div class="drawer-seccion-titulo">🏅 Certificados (${misCerts.length})</div>
                ${misCerts.length === 0 ? '<div class="drawer-empty">Sin certificados aún</div>' :
                    misCerts.map(c => `
                    <div class="drawer-dato-row">
                        <span class="drawer-dato-label">${c.nombre_idioma} ${c.nombre_nivel}</span>
                        <span class="drawer-dato-valor">${c.fecha_emision}</span>
                    </div>`).join('')}
            </div>`;
    } catch (err) {
        document.getElementById('drawer-body').innerHTML = `<p class="error">Error: ${err.message}</p>`;
    }
}

function datoRow(label, valor) {
    return `<div class="drawer-dato-row">
        <span class="drawer-dato-label">${label}</span>
        <span class="drawer-dato-valor">${valor}</span>
    </div>`;
}

function cerrarDrawer() {
    document.getElementById('lc-overlay')?.classList.remove('activo');
    document.getElementById('lc-drawer')?.classList.remove('activo');
}

/* ================================================================
   12. BADGES DE NOTIFICACIÓN EN EL MENÚ
   ================================================================ */
async function cargarNotificacionesMenu() {
    try {
        const [grupos, inscripciones] = await Promise.all([
            fetch('api/grupos.php?accion=listar').then(r => r.ok ? r.json() : []),
            fetch('api/inscripciones.php?accion=listar').then(r => r.ok ? r.json() : []),
        ]);
        if (!Array.isArray(grupos) || !Array.isArray(inscripciones)) return;

        const casiLlenos = grupos.filter(g => {
            if (g.estado != 1) return false;
            const count = inscripciones.filter(i => i.id_grupo == g.id_grupo && i.estado == 1).length;
            return g.cupo_maximo > 0 && (count / g.cupo_maximo) >= 0.8;
        }).length;

        if (casiLlenos > 0) {
            const enlace = document.querySelector('#menu-lateral [data-modulo="grupos"]');
            if (enlace && !enlace.querySelector('.notif-badge')) {
                const badge = document.createElement('span');
                badge.className  = 'notif-badge';
                badge.textContent = casiLlenos;
                badge.title       = `${casiLlenos} grupo(s) con cupo ≥ 80%`;
                enlace.appendChild(badge);
            }
        }
    } catch (_) { /* silencioso */ }
}

/* ================================================================
   13. ATAJOS DE TECLADO
   ================================================================ */
const MODULOS_ATAJOS = ['inicio','estudiantes','docentes','idiomas','niveles','grupos','inscripciones','evaluaciones','certificados'];

function manejarAtajos(e) {
    // Ignorar si se está escribiendo en un input/textarea
    if (['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) return;

    if (e.ctrlKey && e.key >= '1' && e.key <= '9') {
        const idx = parseInt(e.key) - 1;
        if (idx < MODULOS_ATAJOS.length) {
            e.preventDefault();
            cargarModulo(MODULOS_ATAJOS[idx]);
        }
    }
    if (e.key === 'Escape') {
        // Cerrar modal o drawer
        cerrarDrawer();
        document.getElementById('modal-confirm')?.classList.remove('activo');
        // Limpiar formulario activo
        const form = document.querySelector('#contenido-principal form');
        if (form) {
            form.reset();
            form.querySelectorAll('input[type="hidden"]').forEach(h => h.value = '');
        }
    }
    if (e.ctrlKey && e.key === 'b') {
        e.preventDefault();
        const busqueda = document.querySelector('#contenido-principal .input-busqueda');
        if (busqueda) { busqueda.focus(); busqueda.select(); }
    }
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        if (MEJORAS.moduloActual && MEJORAS.moduloActual !== 'inicio') {
            exportarCSV(MEJORAS.moduloActual);
        }
    }
}

/* ================================================================
   INICIALIZACIÓN — DOMContentLoaded
   ================================================================ */
document.addEventListener('DOMContentLoaded', () => {
    // Agregar "Inicio" al menú lateral
    const menuUl = document.querySelector('#menu-lateral ul');
    if (menuUl) {
        const li = document.createElement('li');
        li.innerHTML = '<a href="#" data-modulo="inicio">🏠 Inicio</a>';
        menuUl.insertBefore(li, menuUl.firstChild);
        li.querySelector('a').addEventListener('click', e => {
            e.preventDefault();
            cargarModulo('inicio');
        });
    }

    // Atajos de teclado
    document.addEventListener('keydown', manejarAtajos);

    // Observar login exitoso para cargar notificaciones
    const spanUsuario = document.getElementById('usuario-activo');
    if (spanUsuario) {
        const obs = new MutationObserver(() => {
            if (spanUsuario.textContent.trim()) {
                cargarNotificacionesMenu();
                obs.disconnect();
            }
        });
        obs.observe(spanUsuario, { childList: true, characterData: true, subtree: true });
    }

    // Tooltip de atajos en header
    const header = document.querySelector('header h1');
    if (header) {
        header.title = 'Atajos: Ctrl+1-9 módulos | Ctrl+E exportar | Ctrl+B buscar | Esc cerrar';
    }
});
