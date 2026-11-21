<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Admin\Concerns\ValidaMantenimiento;
use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\MaintenanceDay;
use App\Models\MaintenanceSchedule;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;

class MaintenanceScheduleController extends Controller
{
    use RespondeAjax;
    use ValidaMantenimiento;

    public function __construct(private readonly MaintenanceService $servicio) {}

    public function index(Maintenance $mantenimiento)
    {
        if (! request()->ajax() && ! request()->wantsJson()) {
            return view('admin.mantenimientos.horarios.index', ['mantenimiento' => $mantenimiento]);
        }

        $filas = $mantenimiento->schedules()->with(['vehiculo', 'responsible'])
            ->orderBy('weekday')->orderBy('start_time')->get()
            ->map(fn (MaintenanceSchedule $horario) => $this->fila($horario));

        return response()->json(['data' => $filas->all()]);
    }

    public function show(Maintenance $mantenimiento, MaintenanceSchedule $horario)
    {
        if (! request()->ajax() && ! request()->wantsJson()) {
            return view('admin.mantenimientos.horarios.show', ['mantenimiento' => $mantenimiento, 'horario' => $horario]);
        }

        return response()->json([
            'horario' => $this->fila($horario),
            'dias' => $horario->dias()->orderBy('date')->get()->map(fn (MaintenanceDay $dia) => [
                'id' => $dia->id,
                'fecha' => $dia->date->toDateString(),
                'estado' => $dia->status,
                'observacion' => $dia->observation,
                'imagen' => $this->urlArchivo($dia->image_path, 'images/sin-imagen.svg'),
            ])->all(),
        ]);
    }

    public function store(Request $request, Maintenance $mantenimiento)
    {
        $horario = $this->servicio->crearHorario($mantenimiento, $this->validadoHorario($request));

        return $this->exito('Horario registrado', 'admin.mantenimientos.index', [
            'horario' => $this->fila($horario),
        ]);
    }

    public function update(Request $request, Maintenance $mantenimiento, MaintenanceSchedule $horario)
    {
        $this->servicio->actualizarHorario($horario, $this->validadoHorario($request));

        return $this->exito('Horario actualizado', 'admin.mantenimientos.index', [
            'horario' => $this->fila($horario->fresh()),
        ]);
    }

    public function destroy(Maintenance $mantenimiento, MaintenanceSchedule $horario)
    {
        $this->servicio->eliminarHorario($horario);

        return $this->exito('Horario eliminado', 'admin.mantenimientos.index');
    }

    private function fila(MaintenanceSchedule $horario): array
    {
        return [
            'id' => $horario->id,
            'mantenimiento_id' => $horario->maintenance_id,
            'vehiculo_id' => $horario->vehicle_id,
            'vehiculo' => $horario->vehiculo?->name,
            'placa' => $horario->vehiculo?->plate,
            'responsable_id' => $horario->responsible_id,
            'responsable' => trim(($horario->responsible?->name ?? '').' '.($horario->responsible?->last_name ?? '')),
            'tipo' => $horario->type,
            'weekday' => (int) $horario->weekday,
            'dia' => MaintenanceSchedule::DIAS[(int) $horario->weekday],
            'inicio' => $this->horaJson($horario->start_time),
            'fin' => $this->horaJson($horario->end_time),
            'total_dias' => $horario->dias()->count(),
            'pendientes' => $horario->dias()->where('status', MaintenanceDay::PENDIENTE)->count(),
            'realizados' => $horario->dias()->where('status', MaintenanceDay::REALIZADO)->count(),
        ];
    }
}
