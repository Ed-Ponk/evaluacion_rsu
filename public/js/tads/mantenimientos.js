(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    function fechaColumna(valor, modo) {
        if (modo === 'sort' || modo === 'type') return valor ?? '';

        const partes = String(valor ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!partes) return '<span class="text-muted">—</span>';

        return `<span class="t-fecha">${partes[3]}/${partes[2]}/${partes[1]}</span>`;
    }

    function plantillaFormulario(mantenimiento, config) {
        const edicion = Boolean(mantenimiento.id);
        const accion = edicion ? `${config.urlRecurso}/${mantenimiento.id}` : config.urlListado;
        const metodo = edicion ? '<input type="hidden" name="_method" value="PUT">' : '';

        return `
<div class="t-alerta-form"></div>
<form method="POST" action="${Tads.escapar(accion)}" class="t-formulario">
    <input type="hidden" name="_token" value="${Tads.escapar(token())}">
    <input type="hidden" name="id" value="${Tads.escapar(mantenimiento.id ?? '')}" data-verificar>
    ${metodo}
    <div data-tads-verificar="${Tads.escapar(config.urlVerificar)}">
        <div class="row g-3">
            <div class="col-12">
                <label for="mntNombre" class="form-label">Nombre</label>
                <input type="text" class="form-control" id="mntNombre" name="name" maxlength="120" required
                    value="${Tads.escapar(mantenimiento.nombre ?? '')}" data-verificar>
            </div>
            <div class="col-md-6">
                <label for="mntInicio" class="form-label">Fecha inicio</label>
                <input type="date" class="form-control" id="mntInicio" name="start_date" required
                    value="${Tads.escapar(mantenimiento.inicio ?? '')}" data-verificar>
            </div>
            <div class="col-md-6">
                <label for="mntFin" class="form-label">Fecha fin</label>
                <input type="date" class="form-control" id="mntFin" name="end_date" required
                    value="${Tads.escapar(mantenimiento.fin ?? '')}" data-verificar>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn t-boton">Guardar</button>
    </div>
</form>`;
    }

    function abrirFormulario(gestor, config, mantenimiento = null) {
        const edicion = mantenimiento !== null;

        gestor.tituloEl.innerHTML = `<i class="bi ${edicion ? 'bi-pencil-square' : 'bi-plus-lg'} me-2"></i>${edicion ? 'Editar mantenimiento' : 'Nuevo mantenimiento'}`;
        gestor.cuerpoEl.innerHTML = plantillaFormulario(mantenimiento ?? {}, config);
        Tads.ventana(gestor.selectorVentana).show();
        Tads.iniciarVerificaciones(gestor.cuerpoEl);
    }

    Tads.Modulos.mantenimientos = {
        init: function (contenedor) {
            const config = {
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlVerificar: contenedor.dataset.urlVerificar,
                puedeEditar: contenedor.dataset.puedeEditar === '1',
                puedeEliminar: contenedor.dataset.puedeEliminar === '1',
            };

            const gestor = new Tads.Gestor({
                entidad: 'mantenimiento',
                icono: 'bi-wrench',
                botonNuevo: '#__mantenimientosSinBotonNuevo',
                urlListado: config.urlListado,
                urlRecurso: config.urlRecurso,
                orden: [[1, 'asc']],
                columnas: [
                    { data: 'nombre', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'inicio', render: fechaColumna },
                    { data: 'fin', render: fechaColumna },
                    { data: 'total_horarios', render: (v) => Tads.insignia(`${v} ${v === 1 ? 'horario' : 'horarios'}`, v > 0 ? 'azul' : 'gris') },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => {
                            const botones = [
                                `<a class="btn t-boton-icono t-info" href="${Tads.escapar(`${config.urlRecurso}/${fila.id}/horarios`)}" data-bs-toggle="tooltip" title="Ver horarios"><i class="bi bi-clock-history"></i><span class="d-none d-lg-inline ms-1">Ver horarios</span></a>`,
                            ];

                            if (config.puedeEditar) {
                                botones.push(`<button type="button" class="btn t-boton-icono t-editar" data-editar-mantenimiento="${fila.id}" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil-square"></i></button>`);
                            }
                            if (config.puedeEliminar) {
                                botones.push(Tads.botonEliminar(fila.id, fila.nombre));
                            }

                            return Tads.acciones(...botones);
                        } },
                ],
            });

            contenedor._gestor = gestor;

            document.getElementById('botonNuevo')?.addEventListener('click', () => abrirFormulario(gestor, config));

            contenedor.addEventListener('click', (evento) => {
                const boton = evento.target.closest('[data-editar-mantenimiento]');
                if (!boton) return;

                const fila = gestor.tabla.row(boton.closest('tr')).data();
                if (fila) abrirFormulario(gestor, config, fila);
            });
        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
