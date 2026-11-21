<?php

namespace App\Support;

use App\Models\MaintenanceSchedule;

final class UsoEnMantenimiento
{
    public static function deVehiculo(int $vehiculoId): bool
    {
        return MaintenanceSchedule::where('vehicle_id', $vehiculoId)->exists();
    }

    public static function dePersona(int $personaId): bool
    {
        return MaintenanceSchedule::where('responsible_id', $personaId)->exists();
    }
}
