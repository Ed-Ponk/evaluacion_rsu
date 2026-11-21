@extends('adminlte::page')

@section('title', 'Mantenimientos programados')

@section('content')
    <x-admin.cabecera
        titulo="Mantenimientos programados"
        subtitulo="Planificación de mantenimiento preventivo y correctivo de la flota."
        icono="bi-wrench"
        :breadcrumb="['Gestión de vehículos' => route('admin.vehiculos.index'), 'Mantenimiento' => null]"
        boton="Nuevo mantenimiento"
    />

    <div class="t-tarjeta"
        id="moduloMantenimientos"
        data-tads-modulo="mantenimientos"
        data-permiso="maintenance"
        data-permisos="{{ auth()->user()->permisosParaVista() }}"
        data-url-listado="{{ route('admin.mantenimientos.index') }}"
        data-url-recurso="{{ url('admin/mantenimientos') }}"
        data-url-verificar="{{ route('admin.mantenimientos.verificar') }}"
        data-puede-editar="{{ auth()->user()->esAdministrador() || auth()->user()->tienePermiso('maintenance.edit') ? '1' : '0' }}"
        data-puede-eliminar="{{ auth()->user()->esAdministrador() || auth()->user()->tienePermiso('maintenance.delete') ? '1' : '0' }}">
        <div class="table-responsive">
            <table class="table t-tabla" id="tabla">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Fecha inicio</th>
                        <th>Fecha fin</th>
                        <th>Horarios</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-lg'])
@endsection
