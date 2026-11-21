<?php

namespace Database\Seeders;

use App\Models\ColorVehiculo;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use App\Services\MaintenanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MantenimientoDemoSeeder extends Seeder
{
    public const VEHICULO_CODIGO = 'MANT-DEMO-001';

    public const RESPONSABLES_DNI = ['94000001', '94000002', '94000003'];

    private const MANTENIMIENTOS = [
        ['name' => 'MANT. DICIEMBRE 2026', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31'],
        ['name' => 'MANT. ENERO 2027', 'start_date' => '2027-01-01', 'end_date' => '2027-01-31'],
    ];

    public function run(): void
    {
        $this->call(TipoPersonalSeeder::class);

        $servicio = new MaintenanceService;
        $vehiculo = $this->vehiculo();
        $responsables = $this->responsables();

        $diciembre = $this->mantenimiento($servicio, self::MANTENIMIENTOS[0]);
        $enero = $this->mantenimiento($servicio, self::MANTENIMIENTOS[1]);

        $horarios = [
            [$diciembre, $vehiculo, $responsables[0], MaintenanceSchedule::PREVENTIVO, 1, '07:00', '09:00'],
            [$diciembre, $vehiculo, $responsables[1], MaintenanceSchedule::LIMPIEZA, 3, '07:00', '09:00'],
            [$enero, $vehiculo, $responsables[2], MaintenanceSchedule::PREVENTIVO, 1, '07:00', '09:00'],
        ];

        foreach ($horarios as [$mantenimiento, $vehiculo, $responsable, $tipo, $weekday, $inicio, $fin]) {
            $this->horario($servicio, $mantenimiento, $vehiculo, $responsable, $tipo, $weekday, $inicio, $fin);
        }
    }

    private function mantenimiento(MaintenanceService $servicio, array $datos): Maintenance
    {
        $mantenimiento = Maintenance::firstWhere('name', $datos['name'])
            ?? Maintenance::whereDate('start_date', $datos['start_date'])
                ->whereDate('end_date', $datos['end_date'])
                ->first();

        if ($mantenimiento === null) {
            return $servicio->crearMantenimiento($datos);
        }

        $actual = [
            'name' => $mantenimiento->name,
            'start_date' => $mantenimiento->start_date->toDateString(),
            'end_date' => $mantenimiento->end_date->toDateString(),
        ];

        if ($actual !== $datos) {
            $servicio->actualizarMantenimiento($mantenimiento, $datos);
            $mantenimiento->refresh();
        }

        return $mantenimiento;
    }

    private function horario(
        MaintenanceService $servicio,
        Maintenance $mantenimiento,
        Vehiculo $vehiculo,
        Personal $responsable,
        string $tipo,
        int $weekday,
        string $inicio,
        string $fin
    ): void {
        $existe = MaintenanceSchedule::where('maintenance_id', $mantenimiento->id)
            ->where('vehicle_id', $vehiculo->id)
            ->where('weekday', $weekday)
            ->where('start_time', $inicio)
            ->exists();

        if ($existe) {
            return;
        }

        $servicio->crearHorario($mantenimiento, [
            'vehicle_id' => $vehiculo->id,
            'responsible_id' => $responsable->id,
            'type' => $tipo,
            'weekday' => $weekday,
            'start_time' => $inicio,
            'end_time' => $fin,
        ]);
    }

    private function vehiculo(): Vehiculo
    {
        $marca = Marca::firstOrCreate(['name' => 'Volvo']);
        $modelo = Modelo::firstOrCreate(['brand_id' => $marca->id, 'name' => 'FM'], ['code' => 'MANT-DEMO-FM']);
        $tipo = TipoVehiculo::firstOrCreate(['name' => 'Compactador']);
        $color = ColorVehiculo::firstOrCreate(['name' => 'Blanco'], ['code' => '#FFFFFF']);

        return Vehiculo::updateOrCreate(
            ['code' => self::VEHICULO_CODIGO],
            [
                'name' => 'Vehículo demo mantenimiento',
                'plate' => 'MDT-001',
                'year' => 2024,
                'load_capacity' => 9.5,
                'fuel_capacity' => 130,
                'compact_capacity' => 7,
                'occupant_capacity' => 3,
                'description' => 'Vehículo de ejemplo para mantenimientos.',
                'status' => true,
                'brand_id' => $marca->id,
                'model_id' => $modelo->id,
                'type_id' => $tipo->id,
                'color_id' => $color->id,
            ],
        );
    }

    private function responsables(): array
    {
        $tipo = TipoPersonal::ayudante();
        $nombres = [
            ['name' => 'Rosa', 'last_name' => 'Mantenimiento Diciembre'],
            ['name' => 'Luis', 'last_name' => 'Mantenimiento Diciembre'],
            ['name' => 'Elena', 'last_name' => 'Mantenimiento Enero'],
        ];

        return collect(self::RESPONSABLES_DNI)->map(function (string $dni, int $indice) use ($tipo, $nombres) {
            return Personal::updateOrCreate(['dni' => $dni], [
                'usertype_id' => $tipo->id,
                'name' => $nombres[$indice]['name'],
                'last_name' => $nombres[$indice]['last_name'],
                'birthdate' => '1991-04-18',
                'email' => 'mant.demo'.($indice + 1).'@rsu.test',
                'address' => 'Av. Principal 123, Chiclayo',
                'password' => Str::random(32),
                'status' => true,
            ]);
        })->all();
    }
}
