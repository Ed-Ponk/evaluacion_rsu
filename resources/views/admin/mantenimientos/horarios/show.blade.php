@extends('adminlte::page')

@section('title', $mantenimiento->name.' – Días programados')

@section('content')
    <x-admin.cabecera
        titulo="{{ $mantenimiento->name }} – Días programados"
        subtitulo="Detalle del horario · Horarios y fechas generadas para el mantenimiento."
        icono="bi-calendar2-week"
        :breadcrumb="[
            'Gestión de vehículos' => route('admin.vehiculos.index'),
            'Mantenimiento' => route('admin.mantenimientos.index'),
            $mantenimiento->name => route('admin.mantenimientos.horarios.index', $mantenimiento),
            \App\Models\MaintenanceSchedule::DIAS[(int) $horario->weekday] => route('admin.mantenimientos.horarios.show', [$mantenimiento, $horario]),
            ($horario->vehiculo?->name ?? 'Vehículo') => $horario->vehiculo ? route('admin.vehiculos.show', $horario->vehiculo) : null,
        ]"
    />

    <div class="t-tarjeta"
        id="moduloDiasMantenimiento"
        data-tads-modulo="mantenimientoDias"
        data-permiso="maintenance"
        data-permisos="{{ auth()->user()->permisosParaVista() }}"
        data-url-listado="{{ route('admin.mantenimientos.horarios.show', [$mantenimiento, $horario]) }}"
        data-url-dias="{{ url('admin/mantenimientos/'.$mantenimiento->id.'/horarios/'.$horario->id.'/dias') }}"
        data-puede-editar="{{ auth()->user()->esAdministrador() || auth()->user()->tienePermiso('maintenance.edit') ? '1' : '0' }}">
        <div class="table-responsive">
            <table class="table t-tabla" id="tabla">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Observación</th>
                        <th>Imagen</th>
                        <th>Estado</th>
                        <th>Editar</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-lg'])
@endsection
