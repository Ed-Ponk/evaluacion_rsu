@extends('adminlte::page')

@section('title', $mantenimiento->name.' / Horarios')

@section('content')
    <x-admin.cabecera
        titulo="{{ $mantenimiento->name }} / Horarios"
        subtitulo="Horarios de mantenimiento: distribución semanal de vehículos, responsables y tipos. Mantenimientos vigentes."
        icono="bi-clock-history"
        :breadcrumb="[
            'Gestión de vehículos' => route('admin.vehiculos.index'),
            'Mantenimiento' => route('admin.mantenimientos.index'),
            $mantenimiento->name => null,
        ]"
        boton="Agregar horario"
    />

    <div class="t-tarjeta"
        id="moduloHorariosMantenimiento"
        data-tads-modulo="mantenimientoHorarios"
        data-permiso="maintenance"
        data-permisos="{{ auth()->user()->permisosParaVista() }}"
        data-url-listado="{{ route('admin.mantenimientos.horarios.index', $mantenimiento) }}"
        data-url-recurso="{{ url('admin/mantenimientos/'.$mantenimiento->id.'/horarios') }}"
        data-url-catalogos="{{ route('admin.mantenimientos.catalogos') }}"
        data-puede-editar="{{ auth()->user()->esAdministrador() || auth()->user()->tienePermiso('maintenance.edit') ? '1' : '0' }}"
        data-puede-eliminar="{{ auth()->user()->esAdministrador() || auth()->user()->tienePermiso('maintenance.delete') ? '1' : '0' }}">
        <div class="table-responsive">
            <table class="table t-tabla" id="tabla">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Vehículo</th>
                        <th>Responsable</th>
                        <th>Tipo</th>
                        <th>Hora inicio</th>
                        <th>Hora fin</th>
                        <th>Ver días</th>
                        <th>Editar</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-lg'])
@endsection
