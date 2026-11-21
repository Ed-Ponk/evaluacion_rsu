<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\Personal;
use App\Services\MaintenanceService;
use Database\Seeders\MantenimientoDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MantenimientoDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_dos_mantenimientos_tres_responsables_y_es_idempotente(): void
    {
        $this->seed(MantenimientoDemoSeeder::class);

        $diciembre = Maintenance::firstWhere('name', 'MANT. DICIEMBRE 2026');
        $enero = Maintenance::firstWhere('name', 'MANT. ENERO 2027');

        $this->assertNotNull($diciembre);
        $this->assertNotNull($enero);
        $this->assertSame('2026-12-01', $diciembre->start_date->toDateString());
        $this->assertSame('2026-12-31', $diciembre->end_date->toDateString());
        $this->assertSame('2027-01-01', $enero->start_date->toDateString());
        $this->assertSame('2027-01-31', $enero->end_date->toDateString());
        $this->assertSame(2, Maintenance::count());
        $this->assertSame(3, MaintenanceSchedule::count());

        $dnis = MantenimientoDemoSeeder::RESPONSABLES_DNI;
        sort($dnis);
        $this->assertSame($dnis, Personal::whereIn('dni', $dnis)->pluck('dni')->sort()->values()->all());
        $this->assertSame(3, MaintenanceSchedule::with('responsible')->get()->pluck('responsible.dni')->unique()->filter()->count());

        foreach (MaintenanceSchedule::with('dias')->get() as $horario) {
            $this->assertGreaterThan(0, $horario->dias->count());
        }

        $this->seed(MantenimientoDemoSeeder::class);

        $this->assertSame(2, Maintenance::count());
        $this->assertSame(3, MaintenanceSchedule::count());
        $this->assertSame(3, Personal::whereIn('dni', $dnis)->count());
    }

    public function test_reutiliza_mantenimiento_existente_con_el_mismo_rango(): void
    {
        $existente = (new MaintenanceService)->crearMantenimiento([
            'name' => 'febrero',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
        ]);

        $this->seed(MantenimientoDemoSeeder::class);

        $this->assertSame(1, Maintenance::whereDate('start_date', '2026-12-01')->whereDate('end_date', '2026-12-31')->count());
        $this->assertSame('MANT. DICIEMBRE 2026', $existente->fresh()->name);
        $this->assertSame(2, Maintenance::count());
        $this->assertSame(3, MaintenanceSchedule::count());
    }

    public function test_no_esta_registrado_en_database_seeder(): void
    {
        $contenido = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString('MantenimientoDemoSeeder', $contenido);
    }
}
