<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\MaintenanceDay;
use App\Models\MaintenanceSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceDayController extends Controller
{
    use RespondeAjax;

    public function show(Maintenance $mantenimiento, MaintenanceSchedule $horario, MaintenanceDay $dia)
    {
        return response()->json(['dia' => $this->fila($dia)]);
    }

    public function update(Request $request, Maintenance $mantenimiento, MaintenanceSchedule $horario, MaintenanceDay $dia)
    {
        $datos = $request->validate([
            'realizado' => ['nullable', 'boolean'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'quitar_imagen' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'image.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
            'image.max' => 'La imagen no puede superar los 2 MB.',
        ]);

        $estado = $request->boolean('realizado') ? MaintenanceDay::REALIZADO : MaintenanceDay::NO_REALIZADO;
        $archivo = $request->file('image');
        $quitar = ! $archivo && $request->boolean('quitar_imagen');
        $anterior = $dia->image_path;

        return DB::transaction(function () use ($dia, $estado, $quitar, $anterior, $archivo, $datos) {
            $guardar = function (?string $ruta) use ($dia, $estado, $quitar, $datos) {
                $dia->update([
                    'status' => $estado,
                    'observation' => $datos['observation'] ?? null,
                    'image_path' => $quitar ? null : ($ruta ?? $dia->image_path),
                ]);

                return $this->exito('Día actualizado', 'admin.mantenimientos.index', [
                    'dia' => $this->fila($dia->fresh()),
                ]);
            };

            $respuesta = $archivo
                ? $this->conArchivoNuevo($archivo, "maintenance_days/{$dia->id}", $guardar, $quitar ? null : $anterior)
                : $guardar(null);

            if ($quitar && $anterior !== null) {
                DB::afterCommit(fn () => $this->borrarArchivo($anterior));
            }

            return $respuesta;
        });
    }

    private function fila(MaintenanceDay $dia): array
    {
        return [
            'id' => $dia->id,
            'schedule_id' => $dia->schedule_id,
            'fecha' => $dia->date->toDateString(),
            'estado' => $dia->status,
            'observacion' => $dia->observation,
            'imagen' => $this->urlArchivo($dia->image_path, 'images/sin-imagen.svg'),
        ];
    }
}
