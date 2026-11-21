<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceSchedule extends Model
{
    protected $table = 'maintenance_schedules';

    public const PREVENTIVO = 'PREVENTIVO';

    public const LIMPIEZA = 'LIMPIEZA';

    public const REPARACION = 'REPARACION';

    public const TIPOS = [self::PREVENTIVO, self::LIMPIEZA, self::REPARACION];

    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    protected $fillable = ['maintenance_id', 'vehicle_id', 'responsible_id', 'type', 'weekday', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class, 'maintenance_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'vehicle_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'responsible_id');
    }

    public function dias(): HasMany
    {
        return $this->hasMany(MaintenanceDay::class, 'schedule_id');
    }
}
