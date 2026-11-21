<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Admin\Concerns\ValidaMantenimiento;
use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\MaintenanceDay;
use App\Models\MaintenanceSchedule;
use App\Models\Personal;
use App\Models\Vehiculo;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MaintenanceController extends Controller
{
    use RespondeAjax;
    use ValidaMantenimiento;

    public function __construct(private readonly MaintenanceService $servicio) {}

    public function index()
    {
        if (! request()->ajax() && ! request()->wantsJson()) {
            return view('admin.mantenimientos.index');
        }

        $filas = Maintenance::orderBy('start_date')->orderBy('id')->get()->map(fn (Maintenance $mantenimiento) => $this->fila($mantenimiento));

        return response()->json(['data' => $filas->all()]);
    }

    public function show(Maintenance $mantenimiento)
    {
        return response()->json([
            'mantenimiento' => $this->fila($mantenimiento),
            'horarios' => $mantenimiento->schedules->map(fn (MaintenanceSchedule $horario) => [
                'id' => $horario->id,
                'dia' => MaintenanceSchedule::DIAS[(int) $horario->weekday],
                'inicio' => $this->horaJson($horario->start_time),
                'fin' => $this->horaJson($horario->end_time),
                'total_dias' => $horario->dias()->count(),
            ])->all(),
        ]);
    }

    public function catalogos()
    {
        return response()->json([
            'vehiculos' => Vehiculo::where('status', true)->orderBy('name')->get()->map(fn (Vehiculo $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'plate' => $v->plate,
            ])->all(),
            'personal' => Personal::where('status', true)->orderBy('name')->get()->map(fn (Personal $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'last_name' => $p->last_name,
            ])->all(),
            'tipos' => array_map(fn (string $tipo) => ['valor' => $tipo, 'texto' => ucfirst(strtolower($tipo))], MaintenanceSchedule::TIPOS),
            'dias' => array_map(fn (int $numero, string $nombre) => ['valor' => $numero, 'texto' => $nombre], array_keys(MaintenanceSchedule::DIAS), MaintenanceSchedule::DIAS),
            'estados' => MaintenanceDay::ESTADOS,
        ]);
    }

    public function verificar(Request $request)
    {
        try {
            $validado = $this->validadoMantenimiento($request);
            $this->servicio->validarMantenimiento($validado, $request->integer('id') ?: null);
        } catch (ValidationException $error) {
            return response()->json(['errores' => $error->errors()]);
        }

        return response()->json(['errores' => []]);
    }

    public function store(Request $request)
    {
        $mantenimiento = $this->servicio->crearMantenimiento($this->validadoMantenimiento($request));

        return $this->exito('Mantenimiento registrado', 'admin.mantenimientos.index', [
            'mantenimiento' => $this->fila($mantenimiento),
        ]);
    }

    public function update(Request $request, Maintenance $mantenimiento)
    {
        $this->servicio->actualizarMantenimiento($mantenimiento, $this->validadoMantenimiento($request));

        return $this->exito('Mantenimiento actualizado', 'admin.mantenimientos.index', [
            'mantenimiento' => $this->fila($mantenimiento->fresh()),
        ]);
    }

    public function destroy(Maintenance $mantenimiento)
    {
        $this->servicio->eliminarMantenimiento($mantenimiento);

        return $this->exito('Mantenimiento eliminado', 'admin.mantenimientos.index');
    }

    private function fila(Maintenance $mantenimiento): array
    {
        return [
            'id' => $mantenimiento->id,
            'nombre' => $mantenimiento->name,
            'inicio' => $mantenimiento->start_date->toDateString(),
            'fin' => $mantenimiento->end_date->toDateString(),
            'total_horarios' => $mantenimiento->schedules()->count(),
            'total_dias' => $mantenimiento->dias()->count(),
            'pendientes' => $mantenimiento->dias()->where('status', MaintenanceDay::PENDIENTE)->count(),
            'realizados' => $mantenimiento->dias()->where('status', MaintenanceDay::REALIZADO)->count(),
            'no_realizados' => $mantenimiento->dias()->where('status', MaintenanceDay::NO_REALIZADO)->count(),
        ];
    }
}
