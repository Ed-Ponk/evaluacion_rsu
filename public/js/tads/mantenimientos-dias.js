(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const FORMATOS = ['image/jpeg', 'image/png', 'image/webp'];
    const MAX_BYTES = 2 * 1024 * 1024;
    const COLOR_ESTADO = { PENDIENTE: 'ambar', REALIZADO: 'verde', NO_REALIZADO: 'rojo' };

    function fechaColumna(valor, modo) {
        if (modo === 'sort' || modo === 'type') return valor ?? '';
        const partes = String(valor ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return partes ? `${partes[3]}/${partes[2]}/${partes[1]}` : '<span class="text-muted">—</span>';
    }

    function observacionColumna(valor) {
        const texto = String(valor ?? '');
        if (!texto.trim()) return '<span class="text-muted">—</span>';
        const corto = texto.length > 70 ? `${texto.slice(0, 70)}…` : texto;
        return `<span data-bs-toggle="tooltip" title="${Tads.escapar(texto)}">${Tads.escapar(corto)}</span>`;
    }

    function imagenColumna(valor) {
        const url = String(valor ?? '');
        if (!url || url.includes('sin-imagen.svg')) return Tads.insignia('Sin imagen', 'gris');
        return `<button type="button" class="btn p-0 border-0" data-ampliar-imagen="${Tads.escapar(url)}" title="Ampliar imagen"><img src="${Tads.escapar(url)}" alt="Imagen del día" class="img-thumbnail" style="width:52px;height:52px;object-fit:cover;"></button>`;
    }

    function imagenActual(valor) {
        const url = String(valor ?? '');
        if (!url || url.includes('sin-imagen.svg')) {
            return '<div class="border rounded d-flex align-items-center justify-content-center text-muted" style="width:112px;height:112px;">Sin imagen</div>';
        }
        return `<button type="button" class="btn p-0 border-0" data-ampliar-imagen="${Tads.escapar(url)}" title="Ampliar imagen"><img src="${Tads.escapar(url)}" alt="Imagen actual" class="img-thumbnail" style="width:112px;height:112px;object-fit:cover;"></button>`;
    }

    function plantillaFormulario(dia, config) {
        const realizado = dia.estado === 'REALIZADO';

        return `
<div class="t-alerta-form"></div>
<form method="POST" action="${Tads.escapar(`${config.urlDias}/${dia.id}`)}" class="t-formulario t-formulario-dia" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="${Tads.escapar(token())}">
    <input type="hidden" name="id" value="${Tads.escapar(dia.id ?? '')}">
    <input type="hidden" name="fecha" value="${Tads.escapar(dia.fecha ?? '')}">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="diaFecha" class="form-label">Fecha</label>
            <input type="text" class="form-control" id="diaFecha" value="${Tads.escapar(fechaColumna(dia.fecha))}" readonly>
        </div>
        <div class="col-12">
            <label for="diaObservacion" class="form-label">Observación</label>
            <div data-error-destino><textarea class="form-control" id="diaObservacion" name="observation" rows="3" maxlength="1000">${Tads.escapar(dia.observacion ?? '')}</textarea></div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Imagen actual</label>
            <div id="previewActual">${imagenActual(dia.imagen)}</div>
        </div>
        <div class="col-md-6">
            <label for="diaImagen" class="form-label">Nueva imagen</label>
            <div data-error-destino><input type="file" class="form-control" id="diaImagen" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></div>
            <div class="form-text">JPG, PNG o WEBP · máximo 2 MB</div>
            <div id="previewNueva" class="mt-2"></div>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="quitar_imagen" id="diaQuitar" value="1">
                <label class="form-check-label" for="diaQuitar">Quitar imagen</label>
            </div>
            <div class="text-danger small mt-1" id="errorImagen" hidden></div>
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="realizado" id="diaRealizado" value="1" ${realizado ? 'checked' : ''}>
                <label class="form-check-label" for="diaRealizado">Marcar como realizado</label>
            </div>
            <div class="form-text">Si no lo marca, el día se guardará como No realizado.</div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn t-boton">Guardar</button>
    </div>
</form>`;
    }

    Tads.plantillaFormularioDia = plantillaFormulario;

    Tads.Modulos.mantenimientoDias = {
        init: function (contenedor) {
            const config = {
                urlListado: contenedor.dataset.urlListado,
                urlDias: contenedor.dataset.urlDias,
                puedeEditar: contenedor.dataset.puedeEditar === '1',
            };

            const ventanaEl = document.querySelector('#ventanaFormulario');
            const tablaEl = contenedor.querySelector('#tabla');
            let urlImagenNueva = null;

            const ampliarImagen = (evento) => {
                const boton = evento.target.closest('[data-ampliar-imagen]');
                if (!boton) return;
                Swal.fire({
                    title: 'Imagen del día',
                    imageUrl: boton.dataset.ampliarImagen,
                    imageAlt: 'Imagen ampliada del día',
                    imageWidth: 480,
                    confirmButtonText: 'Cerrar',
                });
            };
            contenedor.addEventListener('click', ampliarImagen);
            ventanaEl.addEventListener('click', ampliarImagen);

            const tabla = $(tablaEl).DataTable({
                ajax: (parametros, devolver) => {
                    Tads.peticion(config.urlListado).then(({ ok, estado, json }) => {
                        if (!ok) {
                            Tads.alertaDeError(estado, json, 'No se pudieron cargar los días');
                            return devolver({ data: [] });
                        }
                        devolver({ data: json.dias ?? [] });
                    }).catch(() => devolver({ data: [] }));
                },
                columns: [
                    { data: 'fecha', render: fechaColumna },
                    { data: 'observacion', render: observacionColumna },
                    { data: 'imagen', orderable: false, searchable: false, render: imagenColumna },
                    { data: 'estado', render: (v) => Tads.insignia(v, COLOR_ESTADO[v] ?? 'gris') },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => config.puedeEditar
                            ? `<button type="button" class="btn t-boton-icono t-editar" data-editar-dia="${fila.id}" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil-square"></i></button>`
                            : '' },
                ],
                order: [[0, 'asc']],
                pageLength: 15,
                lengthMenu: [10, 15, 25, 50, 100],
                pagingType: 'simple_numbers',
                autoWidth: false,
                processing: true,
                layout: { topStart: 'search', topEnd: 'pageLength', bottomStart: 'info', bottomEnd: 'paging' },
                language: { ...Tads.idioma, searchPlaceholder: 'Buscar día u observación' },
                drawCallback: () => {
                    tablaEl.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
                },
            });

            contenedor._tablaDias = tabla;

            function limpiarPreview() {
                if (urlImagenNueva) URL.revokeObjectURL(urlImagenNueva);
                urlImagenNueva = null;
            }

            function enlazarImagen(formulario) {
                const input = formulario.querySelector('#diaImagen');
                const quitar = formulario.querySelector('#diaQuitar');
                const destino = formulario.querySelector('#previewNueva');
                const error = formulario.querySelector('#errorImagen');

                input.addEventListener('change', () => {
                    limpiarPreview();
                    error.hidden = true;
                    destino.innerHTML = '';
                    const archivo = input.files?.[0];
                    if (!archivo) {
                        quitar.disabled = false;
                        return;
                    }

                    if (!FORMATOS.includes(archivo.type) || archivo.size > MAX_BYTES) {
                        input.value = '';
                        quitar.disabled = false;
                        error.textContent = !FORMATOS.includes(archivo.type)
                            ? 'La imagen debe ser JPG, PNG o WEBP.'
                            : 'La imagen no puede superar los 2 MB.';
                        error.hidden = false;
                        return;
                    }

                    quitar.checked = false;
                    quitar.disabled = true;
                    urlImagenNueva = URL.createObjectURL(archivo);
                    destino.innerHTML = `<button type="button" class="btn p-0 border-0" data-ampliar-imagen="${Tads.escapar(urlImagenNueva)}" title="Ampliar imagen"><img src="${Tads.escapar(urlImagenNueva)}" alt="Vista previa" class="img-thumbnail" style="width:112px;height:112px;object-fit:cover;"></button><div class="small text-muted mt-1">${Tads.escapar(archivo.name)}</div>`;
                });

                quitar.addEventListener('change', () => {
                    if (!quitar.checked) return;
                    input.value = '';
                    limpiarPreview();
                    destino.innerHTML = '';
                    error.hidden = true;
                });
            }

            async function abrir(id) {
                const { ok, estado, json } = await Tads.peticion(`${config.urlDias}/${id}`);
                if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo cargar el día');

                limpiarPreview();
                const dia = json.dia;
                document.querySelector('#tituloVentana').innerHTML = `<i class="bi bi-calendar2-week me-2"></i>Editar día · ${Tads.escapar(fechaColumna(dia.fecha))}`;
                ventanaEl.querySelector('.modal-body').innerHTML = plantillaFormulario(dia, config);
                Tads.ventana('#ventanaFormulario').show();
                enlazarImagen(ventanaEl.querySelector('form.t-formulario-dia'));
            }

            tablaEl.addEventListener('click', (evento) => {
                const boton = evento.target.closest('[data-editar-dia]');
                if (boton && config.puedeEditar) abrir(boton.dataset.editarDia);
            });

            ventanaEl.addEventListener('submit', async (evento) => {
                const formulario = evento.target;
                if (!formulario.matches('form.t-formulario-dia')) return;

                evento.preventDefault();
                Tads.limpiarErrores(formulario);
                const error = formulario.querySelector('#errorImagen');
                error.hidden = true;

                if (!formulario.checkValidity()) return formulario.reportValidity();

                const boton = formulario.querySelector('[type="submit"]');
                const textoOriginal = boton.innerHTML;
                boton.disabled = true;
                boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

                try {
                    const { ok, estado, json } = await Tads.peticion(formulario.action, {
                        metodo: 'POST',
                        cuerpo: new FormData(formulario),
                    });

                    if (ok) {
                        Tads.ventana('#ventanaFormulario').hide();
                        tabla.ajax.reload(null, false);
                        Tads.aviso(json?.mensaje ?? 'Día actualizado');
                        return;
                    }

                    if (estado === 422) return Tads.mostrarErrores(formulario, json);
                    Tads.alertaDeError(estado, json, 'No se pudo actualizar el día');
                } finally {
                    boton.disabled = false;
                    boton.innerHTML = textoOriginal;
                }
            });

            ventanaEl.addEventListener('hidden.bs.modal', () => {
                limpiarPreview();
                ventanaEl.querySelector('.modal-body').innerHTML = '';
            });
        },
    };

    if (typeof Tads.iniciarModulos === 'function') Tads.iniciarModulos();
})(window);
