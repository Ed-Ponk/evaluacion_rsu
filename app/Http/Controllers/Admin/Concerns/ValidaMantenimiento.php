<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\MaintenanceSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

trait ValidaMantenimiento
{
    protected function validadoMantenimiento(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
        ], [
            'name.required' => 'Ingrese el nombre del mantenimiento.',
            'start_date.date_format' => 'Ingrese la fecha de inicio como AAAA-MM-DD.',
            'end_date.date_format' => 'Ingrese la fecha de fin como AAAA-MM-DD.',
        ]);
    }

    protected function validadoHorario(Request $request): array
    {
        return $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'responsible_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNotNull('usertype_id')],
            'type' => ['required', Rule::in(MaintenanceSchedule::TIPOS)],
            'weekday' => ['required', 'integer', Rule::in(array_keys(MaintenanceSchedule::DIAS))],
            'start_time' => ['required', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['required', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
        ], [
            'vehicle_id.exists' => 'El vehículo seleccionado no existe.',
            'responsible_id.exists' => 'El responsable debe ser personal registrado.',
            'type.in' => 'Seleccione un tipo de mantenimiento válido.',
            'weekday.in' => 'Seleccione un día válido (1 = lunes, 7 = domingo).',
            'start_time.regex' => 'Ingrese la hora de inicio como HH:MM.',
            'end_time.regex' => 'Ingrese la hora de fin como HH:MM.',
        ]);
    }

    protected function horaJson(?string $hora): ?string
    {
        return blank($hora) ? null : Carbon::parse($hora)->format('H:i');
    }
}
