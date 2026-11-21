(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const COLOR_TIPO = { PREVENTIVO: 'verde', LIMPIEZA: 'turquesa', REPARACION: 'ambar' };

    function opciones(items, valorClave, texto, seleccionado, actual = null) {
        const valorActual = String(seleccionado ?? '');
        const lista = [...items];
        if (actual && !lista.some((item) => String(item[valorClave]) === valorActual)) {
            lista.unshift({ [valorClave]: valorActual, texto: `${actual.texto} (inactivo)` });
        }
        return `<option value="">Seleccione...</option>${lista.map((item) => {
            const valor = String(item[valorClave]);
            const etiqueta = item.texto ?? texto(item);
            const seleccion = valor === valorActual ? ' selected' : '';
            return `<option value="${valor}"${seleccion}>${Tads.escapar(etiqueta)}</option>`;
        }).join('')}`;
    }

    function campo(name, etiqueta, control, extra = '') {
        return `<div class="col-md-6">
    <label for="h_${name}" class="form-label">${etiqueta}</label>
    <div data-error-destino>${control}</div>
    ${extra}
</div>`;
    }

    function plantillaFormulario(horario, config, catalogos) {
        const edicion = Boolean(horario.id);
        const accion = edicion ? `${config.urlRecurso}/${horario.id}` : config.urlRecurso;
        const metodo = edicion ? '<input type="hidden" name="_method" value="PUT">' : '';

        return `
<div class="t-alerta-form"></div>
<form method="POST" action="${Tads.escapar(accion)}" class="t-formulario">
    <input type="hidden" name="_token" value="${Tads.escapar(token())}">
    <input type="hidden" name="id" value="${Tads.escapar(horario.id ?? '')}" data-verificar>
    ${metodo}
    <div class="row g-3">
        ${campo('vehicle_id', 'Vehículo', `<select class="form-select" id="h_vehicle_id" name="vehicle_id" required>${opciones(catalogos.vehiculos ?? [], 'id', (v) => `${v.name} (${v.plate})`, horario.vehiculo_id, edicion ? { id: horario.vehiculo_id, texto: `${horario.vehiculo ?? ''} (${horario.placa ?? ''})` } : null)}</select>`)}
        ${campo('responsible_id', 'Responsable', `<select class="form-select" id="h_responsible_id" name="responsible_id" required>${opciones(catalogos.personal ?? [], 'id', (p) => `${p.name} ${p.last_name ?? ''}`, horario.responsable_id, edicion ? { id: horario.responsable_id, texto: horario.responsable ?? '' } : null)}</select>`)}
        ${campo('type', 'Tipo de mantenimiento', `<select class="form-select" id="h_type" name="type" required>${opciones(catalogos.tipos ?? [], 'valor', (t) => t.texto, horario.tipo)}</select>`)}
        ${campo('weekday', 'Día de la semana', `<select class="form-select" id="h_weekday" name="weekday" required>${opciones(catalogos.dias ?? [], 'valor', (d) => d.texto, horario.weekday)}</select>`)}
        ${campo('start_time', 'Hora de inicio', `<input type="time" class="form-control" id="h_start_time" name="start_time" value="${Tads.escapar(horario.inicio ?? '')}" required>`, '<small class="form-text text-muted">Los horarios contiguos son permitidos; el solape se rechaza.</small>')}
        ${campo('end_time', 'Hora de fin', `<input type="time" class="form-control" id="h_end_time" name="end_time" value="${Tads.escapar(horario.fin ?? '')}" required>`)}
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn t-boton">Guardar</button>
    </div>
</form>`;
    }

    Tads.plantillaFormularioHorario = plantillaFormulario;

    Tads.Modulos.mantenimientoHorarios = {
        init: function (contenedor) {
            const config = {
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCatalogos: contenedor.dataset.urlCatalogos,
                puedeEditar: contenedor.dataset.puedeEditar === '1',
                puedeEliminar: contenedor.dataset.puedeEliminar === '1',
            };

            const gestor = new Tads.Gestor({
                entidad: 'horario',
                icono: 'bi-clock-history',
                botonNuevo: '#__mantenimientosHorariosSinBotonNuevo',
                urlListado: config.urlListado,
                urlRecurso: config.urlRecurso,
                orden: [[0, 'asc'], [4, 'asc']],
                columnas: [
                    { data: 'dia', render: (v, tipo, fila) => (tipo === 'sort' || tipo === 'type') ? fila.weekday : `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'vehiculo', render: (v, tipo, fila) => `${Tads.escapar(v)}<small class="d-block text-muted">${Tads.escapar(fila.placa)}</small>` },
                    { data: 'responsable', render: (v) => Tads.escapar(v) },
                    { data: 'tipo', render: (v) => Tads.insignia(v, COLOR_TIPO[v] ?? 'gris') },
                    { data: 'inicio', render: (v) => `<span class="t-fecha">${Tads.escapar(v)}</span>` },
                    { data: 'fin', render: (v) => `<span class="t-fecha">${Tads.escapar(v)}</span>` },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => `<a class="btn t-boton-icono t-info" href="${Tads.escapar(`${config.urlRecurso}/${fila.id}`)}" data-bs-toggle="tooltip" title="Ver días"><i class="bi bi-calendar2-week"></i><span class="d-none d-lg-inline ms-1">Ver días (${fila.total_dias})</span></a>` },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => config.puedeEditar
                            ? `<button type="button" class="btn t-boton-icono t-editar" data-editar-horario="${fila.id}" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil-square"></i></button>`
                            : '' },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => config.puedeEliminar
                            ? `<button type="button" class="btn t-boton-icono t-eliminar" data-eliminar-horario="${fila.id}" data-bs-toggle="tooltip" title="Eliminar"><i class="bi bi-trash3-fill"></i></button>`
                            : '' },
                ],
            });

            contenedor._gestor = gestor;

            let promesaCatalogos = null;
            const catalogos = () => {
                if (!promesaCatalogos) {
                    promesaCatalogos = Tads.peticion(config.urlCatalogos).then(({ ok, estado, json }) => {
                        if (!ok) throw { estado, json };
                        return json;
                    }).catch((error) => {
                        promesaCatalogos = null;
                        throw error;
                    });
                }
                return promesaCatalogos;
            };

            async function abrirFormulario(horario = null, detalle = null) {
                let catalogosJson;
                try {
                    catalogosJson = await catalogos();
                } catch (error) {
                    Tads.alertaDeError(error?.estado, error?.json, 'No se pudieron cargar los catálogos');
                    return;
                }

                const edicion = horario !== null;
                const generados = detalle ? `${detalle.dias.length} días generados` : 'Los días se generan al guardar';
                gestor.tituloEl.innerHTML = `<i class="bi ${edicion ? 'bi-pencil-square' : 'bi-plus-lg'} me-2"></i>${edicion ? 'Editar horario' : 'Nuevo horario'} <small class="text-muted">· ${generados}</small>`;
                gestor.cuerpoEl.innerHTML = plantillaFormulario(horario ?? {}, config, catalogosJson);
                Tads.ventana(gestor.selectorVentana).show();
            }

            async function abrirEdicion(id) {
                const { ok, estado, json } = await Tads.peticion(`${config.urlRecurso}/${id}`);
                if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo cargar el horario');

                await abrirFormulario(json.horario, json);
            }

            async function eliminar(fila) {
                if (fila.realizados > 0) {
                    return Swal.fire({
                        title: 'No se puede eliminar',
                        html: `El horario del <strong>${Tads.escapar(fila.dia)}</strong> tiene <strong>${fila.realizados}</strong> días marcados como realizados. Primero deben revertirse esos días.`,
                        icon: 'error',
                        confirmButtonText: 'Entendido',
                    });
                }

                const decision = await Swal.fire({
                    title: '¿Eliminar horario?',
                    html: `Se eliminará el horario del <strong>${Tads.escapar(fila.dia)}</strong> y sus <strong>${fila.total_dias}</strong> días generados. Esta acción no se puede deshacer.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                });
                if (!decision.isConfirmed) return;

                const { ok, estado, json } = await Tads.peticion(`${config.urlRecurso}/${fila.id}`, {
                    metodo: 'POST',
                    cuerpo: Tads.cuerpoConMetodo('DELETE'),
                });

                if (ok) {
                    gestor.recargar();
                    return Tads.aviso(json?.mensaje ?? 'Horario eliminado');
                }

                if (estado === 422) {
                    return Swal.fire({ title: 'No se puede eliminar', text: Tads.textoDeError(estado, json), icon: 'error', confirmButtonText: 'Entendido' });
                }

                Tads.alertaDeError(estado, json, 'No se pudo eliminar el horario');
            }

            document.getElementById('botonNuevo')?.addEventListener('click', () => abrirFormulario());

            contenedor.querySelector('#tabla')?.addEventListener('click', (evento) => {
                const editar = evento.target.closest('[data-editar-horario]');
                if (editar) {
                    const fila = gestor.tabla.row(editar.closest('tr')).data();
                    if (fila) abrirEdicion(fila.id);
                    return;
                }

                const eliminarBoton = evento.target.closest('[data-eliminar-horario]');
                if (eliminarBoton) {
                    const fila = gestor.tabla.row(eliminarBoton.closest('tr')).data();
                    if (fila) eliminar(fila);
                }
            });
        },
    };

    if (typeof Tads.iniciarModulos === 'function') Tads.iniciarModulos();
})(window);
