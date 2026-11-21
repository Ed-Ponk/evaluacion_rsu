<?php

namespace Tests\Feature;

use App\Models\ColorVehiculo;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\MaintenanceService;
use App\Support\CatalogoPermisos;
use Database\Seeders\TipoPersonalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MantenimientosFase4aTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TipoPersonalSeeder::class);
    }

    private function vehiculo(bool $activo = true): Vehiculo
    {
        $marca = Marca::firstOrCreate(['name' => 'Volvo']);
        $modelo = Modelo::firstOrCreate(['brand_id' => $marca->id, 'name' => 'FM'], ['code' => 'FM-VOL']);

        return Vehiculo::create([
            'name' => 'Vehículo de prueba',
            'code' => 'VEH-F4A'.random_int(1000, 9999),
            'plate' => 'F4A'.random_int(100, 999),
            'year' => 2022,
            'load_capacity' => 8.5,
            'fuel_capacity' => 120,
            'compact_capacity' => 6,
            'occupant_capacity' => 3,
            'status' => $activo,
            'brand_id' => $marca->id,
            'model_id' => $modelo->id,
            'type_id' => TipoVehiculo::firstOrCreate(['name' => 'Compactador'])->id,
            'color_id' => ColorVehiculo::firstOrCreate(['name' => 'Blanco'], ['code' => '#FFFFFF'])->id,
        ]);
    }

    private function persona(bool $activo = true): Personal
    {
        $dni = (string) random_int(50000000, 59999999);

        return Personal::create([
            'dni' => $dni,
            'usertype_id' => TipoPersonal::ayudante()->id,
            'name' => 'Responsable',
            'last_name' => 'Fase4a',
            'birthdate' => '1990-01-01',
            'email' => "f4a{$dni}@prueba.test",
            'address' => 'Av. Principal 123',
            'password' => 'secret123',
            'status' => $activo,
        ]);
    }

    private function mantenimiento(): Maintenance
    {
        return (new MaintenanceService)->crearMantenimiento([
            'name' => 'Mantenimiento Fase 4a',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
        ]);
    }

    private function datosHorario(MaintenanceSchedule $horario, array $cambios = []): array
    {
        return $cambios + [
            'vehicle_id' => $horario->vehicle_id,
            'responsible_id' => $horario->responsible_id,
            'type' => $horario->type,
            'weekday' => $horario->weekday,
            'start_time' => $horario->start_time,
            'end_time' => $horario->end_time,
        ];
    }

    public function test_el_servicio_exige_responsable_activo(): void
    {
        $servicio = new MaintenanceService;
        $mantenimiento = $this->mantenimiento();
        $inactivo = $this->persona(false);

        try {
            $servicio->crearHorario($mantenimiento, [
                'vehicle_id' => $this->vehiculo()->id,
                'responsible_id' => $inactivo->id,
                'type' => MaintenanceSchedule::PREVENTIVO,
                'weekday' => 1,
                'start_time' => '08:00',
                'end_time' => '10:00',
            ]);
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('responsible_id', $error->errors());
            $this->assertSame('El responsable debe ser personal registrado y activo.', $error->errors()['responsible_id'][0]);

            return;
        }

        $this->fail('Se esperaba rechazo por responsable inactivo.');
    }

    public function test_actualizar_horario_no_exige_vehiculo_activo_cuando_no_cambia(): void
    {
        $servicio = new MaintenanceService;
        $vehiculo = $this->vehiculo();
        $horario = $servicio->crearHorario($this->mantenimiento(), [
            'vehicle_id' => $vehiculo->id,
            'responsible_id' => $this->persona()->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $vehiculo->update(['status' => false]);

        $servicio->actualizarHorario($horario, $this->datosHorario($horario, [
            'start_time' => '08:30',
            'end_time' => '10:30',
        ]));

        $this->assertSame('08:30', $horario->fresh()->start_time);
        $this->assertSame($vehiculo->id, $horario->fresh()->vehicle_id);
    }

    public function test_actualizar_horario_rechaza_vehiculo_inactivo_cuando_cambia(): void
    {
        $servicio = new MaintenanceService;
        $horario = $servicio->crearHorario($this->mantenimiento(), [
            'vehicle_id' => $this->vehiculo()->id,
            'responsible_id' => $this->persona()->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        $inactivo = $this->vehiculo(false);

        try {
            $servicio->actualizarHorario($horario, $this->datosHorario($horario, [
                'vehicle_id' => $inactivo->id,
            ]));
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('vehicle_id', $error->errors());

            return;
        }

        $this->fail('Se esperaba rechazo por cambio a vehículo inactivo.');
    }

    public function test_verificar_exige_permiso_de_creacion_o_edicion(): void
    {
        $this->assertSame(
            ['maintenance.create', 'maintenance.edit'],
            CatalogoPermisos::paraRuta('admin.mantenimientos.verificar'),
        );

        $operador = User::factory()->operador()->create();
        $consulta = [
            'name' => 'Validación F4A',
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-31',
        ];

        $this->actingAs($operador)
            ->getJson(route('admin.mantenimientos.verificar', $consulta))
            ->assertForbidden();

        $operador->permisos()->create(['permission' => 'maintenance.view']);

        $this->actingAs($operador->fresh())
            ->getJson(route('admin.mantenimientos.verificar', $consulta))
            ->assertForbidden();

        $operador->permisos()->create(['permission' => 'maintenance.create']);

        $this->actingAs($operador->fresh())
            ->getJson(route('admin.mantenimientos.verificar', $consulta))
            ->assertOk()
            ->assertJsonPath('errores', []);
    }

    public function test_el_menu_de_mantenimientos_usa_la_etiqueta_en_singular(): void
    {
        $grupo = collect(config('adminlte.menu'))->first(fn ($item) => ($item['text'] ?? null) === 'Gestión de vehículos');
        $mantenimiento = collect($grupo['submenu'] ?? [])->firstWhere('route', 'admin.mantenimientos.index');

        $this->assertSame('Mantenimiento', $mantenimiento['text'] ?? null);
    }

    public function test_la_pantalla_de_mantenimientos_rendeza_el_modulo_tads_de_la_fase_4a(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.mantenimientos.index'))
            ->assertOk()
            ->assertViewIs('admin.mantenimientos.index')
            ->assertSee('Mantenimientos programados')
            ->assertSee('Nuevo mantenimiento')
            ->assertSee('Gestión de vehículos')
            ->assertSee('Mantenimiento</li>', false)
            ->assertSee('Nombre</th>', false)
            ->assertSee('Fecha inicio</th>', false)
            ->assertSee('Fecha fin</th>', false)
            ->assertSee('Horarios</th>', false)
            ->assertSee('Acciones</th>', false)
            ->assertSee('data-tads-modulo="mantenimientos"', false)
            ->assertSee('data-url-verificar="'.route('admin.mantenimientos.verificar').'"', false)
            ->assertSee('id="ventanaFormulario"', false);

        $this->assertFileExists(public_path('js/tads/mantenimientos.js'));
        $this->assertContains(
            'js/tads/mantenimientos.js',
            array_column(config('adminlte.plugins.EstilosTads.files'), 'location'),
        );
    }
}
