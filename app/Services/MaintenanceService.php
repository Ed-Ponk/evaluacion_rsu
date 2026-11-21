<?php

namespace App\Services;

use App\Models\Maintenance;
use App\Models\MaintenanceDay;
use App\Models\MaintenanceSchedule;
use App\Models\Personal;
use App\Models\Vehiculo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    public function validarMantenimiento(array $datos, ?int $ignorarId = null): array
    {
        $inicio = $this->fecha($datos['start_date'] ?? '', 'start_date');
        $fin = $this->fecha($datos['end_date'] ?? '', 'end_date');

        $this->validarRangoFechas($inicio, $fin);
        $this->validarSolapeFechas($inicio, $fin, $ignorarId);

        return [
            'name' => trim((string) ($datos['name'] ?? '')),
            'start_date' => $inicio,
            'end_date' => $fin,
        ];
    }

    public function crearMantenimiento(array $datos): Maintenance
    {
        return Maintenance::create($this->validarMantenimiento($datos));
    }

    public function actualizarMantenimiento(Maintenance $mantenimiento, array $datos): Maintenance
    {
        $validado = $this->validarMantenimiento($datos, $mantenimiento->id);
        $inicio = $validado['start_date'];
        $fin = $validado['end_date'];

        $cambiaRango = $inicio !== $mantenimiento->start_date->toDateString()
            || $fin !== $mantenimiento->end_date->toDateString();

        if ($cambiaRango) {
            $this->validarEvidenciaFueraDeRango($mantenimiento, $inicio, $fin);
        }

        return DB::transaction(function () use ($mantenimiento, $validado, $cambiaRango) {
            $mantenimiento->update($validado);

            if ($cambiaRango) {
                foreach ($mantenimiento->schedules as $horario) {
                    $this->sincronizarDias($horario, $mantenimiento, (int) $horario->weekday);
                }
            }

            return $mantenimiento;
        });
    }

    public function eliminarMantenimiento(Maintenance $mantenimiento): void
    {
        $realizado = MaintenanceDay::whereHas('horario', function ($consulta) use ($mantenimiento) {
            $consulta->where('maintenance_id', $mantenimiento->id);
        })->where('status', MaintenanceDay::REALIZADO)->exists();

        if ($realizado) {
            throw ValidationException::withMessages([
                'maintenance' => 'No se puede eliminar: hay días marcados como REALIZADO.',
            ]);
        }

        $rutas = $mantenimiento->dias->pluck('image_path')->filter()->all();

        DB::transaction(fn () => $mantenimiento->delete());

        foreach ($rutas as $ruta) {
            $this->borrarImagen($ruta);
        }
    }

    public function crearHorario(Maintenance $mantenimiento, array $datos): MaintenanceSchedule
    {
        $validado = $this->validarDatosHorario($datos, $mantenimiento);

        return DB::transaction(function () use ($mantenimiento, $validado) {
            $horario = $mantenimiento->schedules()->create($validado);
            $this->sincronizarDias($horario, $mantenimiento, (int) $validado['weekday']);

            return $horario;
        });
    }

    public function actualizarHorario(MaintenanceSchedule $horario, array $datos): MaintenanceSchedule
    {
        $mantenimiento = $horario->mantenimiento;
        $validado = $this->validarDatosHorario($datos, $mantenimiento, $horario);

        $cambiaWeekday = (int) $validado['weekday'] !== (int) $horario->weekday;

        if ($cambiaWeekday && $this->tieneEvidencia($horario)) {
            throw ValidationException::withMessages([
                'weekday' => 'No se puede cambiar el día: hay días con evidencia (estado, imagen u observación).',
            ]);
        }

        return DB::transaction(function () use ($horario, $validado, $mantenimiento, $cambiaWeekday) {
            $horario->update($validado);

            if ($cambiaWeekday) {
                $this->sincronizarDias($horario, $mantenimiento, (int) $validado['weekday']);
            }

            return $horario;
        });
    }

    public function eliminarHorario(MaintenanceSchedule $horario): void
    {
        $realizado = $horario->dias()->where('status', MaintenanceDay::REALIZADO)->exists();

        if ($realizado) {
            throw ValidationException::withMessages([
                'schedule' => 'No se puede eliminar: hay días marcados como REALIZADO.',
            ]);
        }

        $rutas = $horario->dias()->whereNotNull('image_path')->pluck('image_path')->all();

        DB::transaction(fn () => $horario->delete());

        foreach ($rutas as $ruta) {
            $this->borrarImagen($ruta);
        }
    }

    private function validarRangoFechas(string $inicio, string $fin): void
    {
        if (Carbon::parse($inicio)->gt(Carbon::parse($fin))) {
            throw ValidationException::withMessages([
                'start_date' => 'La fecha de inicio no puede ser posterior a la fecha de fin.',
            ]);
        }
    }

    private function validarSolapeFechas(string $inicio, string $fin, ?int $ignorarId = null): void
    {
        $consulta = Maintenance::query()
            ->where('start_date', '<=', $fin)
            ->where('end_date', '>=', $inicio);

        if ($ignorarId !== null) {
            $consulta->where('id', '!=', $ignorarId);
        }

        if ($consulta->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'El rango de fechas se solapa con otro mantenimiento (el día compartido cuenta como solape).',
            ]);
        }
    }

    private function validarDatosHorario(array $datos, Maintenance $mantenimiento, ?MaintenanceSchedule $horario = null): array
    {
        $inicio = $this->hora($datos['start_time'] ?? '', 'start_time');
        $fin = $this->hora($datos['end_time'] ?? '', 'end_time');

        if ($inicio >= $fin) {
            throw ValidationException::withMessages([
                'end_time' => 'La hora de fin debe ser posterior a la hora de inicio.',
            ]);
        }

        $weekday = (int) ($datos['weekday'] ?? 0);
        if (! array_key_exists($weekday, MaintenanceSchedule::DIAS)) {
            throw ValidationException::withMessages(['weekday' => 'Seleccione un día válido (1 = lunes, 7 = domingo).']);
        }

        $vehiculoId = (int) ($datos['vehicle_id'] ?? 0);
        $vehiculoCambio = $horario === null || $vehiculoId !== (int) $horario->vehicle_id;
        if ($vehiculoCambio && ! Vehiculo::whereKey($vehiculoId)->where('status', true)->exists()) {
            throw ValidationException::withMessages(['vehicle_id' => 'El vehículo seleccionado no existe o no está activo.']);
        }

        $responsableId = (int) ($datos['responsible_id'] ?? 0);
        $responsableCambio = $horario === null || $responsableId !== (int) $horario->responsible_id;
        if ($responsableCambio && ! Personal::whereKey($responsableId)->where('status', true)->exists()) {
            throw ValidationException::withMessages(['responsible_id' => 'El responsable debe ser personal registrado y activo.']);
        }

        $tipo = strtoupper(trim((string) ($datos['type'] ?? '')));
        if (! in_array($tipo, MaintenanceSchedule::TIPOS, true)) {
            throw ValidationException::withMessages(['type' => 'Seleccione un tipo de mantenimiento válido.']);
        }

        $this->validarSolapeHorarios($mantenimiento->id, $vehiculoId, $weekday, $inicio, $fin, $horario?->id);

        return [
            'maintenance_id' => $mantenimiento->id,
            'vehicle_id' => $vehiculoId,
            'responsible_id' => $responsableId,
            'type' => $tipo,
            'weekday' => $weekday,
            'start_time' => $inicio,
            'end_time' => $fin,
        ];
    }

    private function validarSolapeHorarios(int $mantenimientoId, int $vehiculoId, int $weekday, string $inicio, string $fin, ?int $ignorarId): void
    {
        $consulta = MaintenanceSchedule::where('maintenance_id', $mantenimientoId)
            ->where('vehicle_id', $vehiculoId)
            ->where('weekday', $weekday);

        if ($ignorarId !== null) {
            $consulta->where('id', '!=', $ignorarId);
        }

        $choque = $consulta->get()->first(function (MaintenanceSchedule $otro) use ($inicio, $fin) {
            return $this->aMinutos($otro->start_time) < $this->aMinutos($fin)
                && $this->aMinutos($otro->end_time) > $this->aMinutos($inicio);
        });

        if ($choque !== null) {
            throw ValidationException::withMessages([
                'start_time' => 'Este mantenimiento ya tiene un horario que se cruza para ese vehículo y día (los contiguos sí son permitidos).',
            ]);
        }
    }

    private function validarEvidenciaFueraDeRango(Maintenance $mantenimiento, string $inicio, string $fin): void
    {
        $afectados = MaintenanceDay::whereHas('horario', function ($consulta) use ($mantenimiento) {
            $consulta->where('maintenance_id', $mantenimiento->id);
        })->where(function ($consulta) {
            $this->filtrarEvidencia($consulta);
        })->where(function ($consulta) use ($inicio, $fin) {
            $consulta->where('date', '<', $inicio)->orWhere('date', '>', $fin);
        })->get();

        if ($afectados->isNotEmpty()) {
            $fecha = $afectados->first()->date->toDateString();

            throw ValidationException::withMessages([
                'maintenance' => 'No se puede cambiar el rango: hay días con evidencia fuera del nuevo periodo (por ejemplo '.$fecha.').',
            ]);
        }
    }

    private function sincronizarDias(MaintenanceSchedule $horario, Maintenance $mantenimiento, int $weekday): void
    {
        $objetivo = $this->fechasDeDia($mantenimiento->start_date->toDateString(), $mantenimiento->end_date->toDateString(), $weekday);

        $actuales = $horario->dias()->pluck('date')->map(function ($fecha) {
            return $fecha instanceof Carbon ? $fecha->toDateString() : (string) $fecha;
        })->all();

        $rutas = [];

        foreach (array_diff($actuales, $objetivo) as $fecha) {
            $dia = $horario->dias()->where('date', $fecha)->first();
            $dia?->delete();

            if ($dia?->image_path !== null) {
                $rutas[] = $dia->image_path;
            }
        }

        foreach ($rutas as $ruta) {
            DB::afterCommit(fn () => $this->borrarImagen($ruta));
        }

        foreach (array_diff($objetivo, $actuales) as $fecha) {
            MaintenanceDay::create(['schedule_id' => $horario->id, 'date' => $fecha, 'status' => MaintenanceDay::PENDIENTE]);
        }
    }

    private function fechasDeDia(string $inicio, string $fin, int $weekday): array
    {
        $fechas = [];
        $actual = Carbon::parse($inicio)->startOfDay();
        $ultimo = Carbon::parse($fin)->startOfDay();

        while ($actual->lte($ultimo)) {
            if ($actual->dayOfWeekIso === $weekday) {
                $fechas[] = $actual->toDateString();
            }

            $actual->addDay();
        }

        return $fechas;
    }

    private function tieneEvidencia(MaintenanceSchedule $horario): bool
    {
        return $horario->dias()->where(function ($consulta) {
            $this->filtrarEvidencia($consulta);
        })->exists();
    }

    private function filtrarEvidencia($consulta): void
    {
        $consulta->where('status', '!=', MaintenanceDay::PENDIENTE)
            ->orWhereNotNull('image_path')
            ->orWhere(function ($consulta) {
                $consulta->whereNotNull('observation')->where('observation', '!=', '');
            });
    }

    private function fecha(mixed $valor, string $campo): string
    {
        $texto = trim((string) $valor);

        try {
            $fecha = Carbon::createFromFormat('!Y-m-d', $texto);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$campo => 'Ingrese una fecha válida (YYYY-MM-DD).']);
        }

        if ($fecha === false || $fecha->format('Y-m-d') !== $texto) {
            throw ValidationException::withMessages([$campo => 'Ingrese una fecha válida (YYYY-MM-DD).']);
        }

        return $texto;
    }

    private function hora(mixed $valor, string $campo): string
    {
        $texto = trim((string) $valor);

        if (! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $texto, $partes)) {
            throw ValidationException::withMessages([$campo => 'Ingrese una hora válida (HH:MM).']);
        }

        $hora = (int) $partes[1];
        $minuto = (int) $partes[2];

        if ($hora > 23 || $minuto > 59) {
            throw ValidationException::withMessages([$campo => 'Ingrese una hora válida (HH:MM).']);
        }

        return sprintf('%02d:%02d', $hora, $minuto);
    }

    private function aMinutos(string $hora): int
    {
        $partes = explode(':', $hora);

        return ((int) $partes[0]) * 60 + ((int) ($partes[1] ?? 0));
    }

    private function borrarImagen(?string $ruta): void
    {
        if (blank($ruta) || ! str_starts_with($ruta, '/uploads/') || str_contains($ruta, '..')) {
            return;
        }

        $fisica = public_path(ltrim($ruta, '/'));

        if (is_file($fisica)) {
            @unlink($fisica);
        }
    }
}
