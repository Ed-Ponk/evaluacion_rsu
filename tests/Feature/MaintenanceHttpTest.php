<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Models\ColorVehiculo;
use App\Models\Maintenance;
use App\Models\MaintenanceDay;
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
use App\Support\UsoEnMantenimiento;
use Database\Seeders\TipoPersonalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MaintenanceHttpTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceService $servicio;

    private int $contador = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TipoPersonalSeeder::class);
        $this->servicio = new MaintenanceService;
        $this->actingAs(User::factory()->create());
    }

    private function ajax(string $metodo, string $url, array $datos = [])
    {
        return $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->{$metodo}($url, $datos);
    }

    private function vehiculo(bool $activo = true): Vehiculo
    {
        $this->contador++;
        $marca = Marca::firstOrCreate(['name' => 'Volvo']);
        $modelo = Modelo::firstOrCreate(['brand_id' => $marca->id, 'name' => 'FM'], ['code' => 'FM-VOL']);

        return Vehiculo::create([
            'name' => 'Vehículo '.$this->contador,
            'code' => 'VEH-'.$this->contador,
            'plate' => 'AB'.str_pad((string) $this->contador, 4, '0', STR_PAD_LEFT),
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

    private function persona(): Personal
    {
        $this->contador++;
        $dni = (string) (50000000 + $this->contador);

        return Personal::create([
            'dni' => $dni,
            'usertype_id' => TipoPersonal::ayudante()->id,
            'name' => 'Responsable',
            'last_name' => 'N'.$dni,
            'birthdate' => '1990-01-01',
            'email' => "responsable{$dni}@prueba.test",
            'address' => 'Av. Principal 123',
            'password' => 'secret123',
            'status' => true,
        ]);
    }

    private function mantenimiento(string $inicio = '2026-12-01', string $fin = '2026-12-31'): Maintenance
    {
        $this->contador++;

        return $this->servicio->crearMantenimiento([
            'name' => 'Mantenimiento '.$this->contador,
            'start_date' => $inicio,
            'end_date' => $fin,
        ]);
    }

    private function datosHorario(Vehiculo $vehiculo, Personal $persona): array
    {
        return [
            'vehicle_id' => $vehiculo->id,
            'responsible_id' => $persona->id,
            'type' => MaintenanceSchedule::PREVENTIVO,
            'weekday' => 1,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ];
    }

    private function guardarArchivoReal(): string
    {
        $subida = new class
        {
            use RespondeAjax;

            public function guardar(UploadedFile $archivo, string $carpeta): string
            {
                return $this->guardarArchivo($archivo, $carpeta);
            }
        };

        return $subida->guardar(UploadedFile::fake()->image('vieja.jpg'), 'maintenance_days');
    }

    public function test_listado_catalogos_y_verificacion_por_ajax(): void
    {
        $mantenimiento = $this->mantenimiento();

        $this->ajax('get', route('admin.mantenimientos.index'))
            ->assertOk()->assertJsonPath('data.0.nombre', 'Mantenimiento 1');

        $catalogos = $this->ajax('get', route('admin.mantenimientos.catalogos'))->assertOk()->json();
        $this->assertArrayHasKey('vehiculos', $catalogos);
        $this->assertArrayHasKey('personal', $catalogos);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_column($catalogos['dias'], 'valor'));

        $this->ajax('get', route('admin.mantenimientos.verificar', ['name' => 'x', 'start_date' => '2026-13-01', 'end_date' => 'mal']))
            ->assertOk()->assertJsonStructure(['errores' => ['start_date', 'end_date']]);

        $rango = ['name' => 'x', 'start_date' => '2026-12-10', 'end_date' => '2026-12-20'];
        $this->ajax('get', route('admin.mantenimientos.verificar', $rango))
            ->assertOk()->assertJsonStructure(['errores' => ['start_date']]);
        $this->ajax('get', route('admin.mantenimientos.verificar', $rango + ['id' => $mantenimiento->id]))
            ->assertOk()->assertExactJson(['errores' => []]);
    }

    public function test_catalogos_solo_devuelve_activos_con_los_campos_esperados(): void
    {
        $activo = $this->vehiculo();
        $inactivo = $this->vehiculo(false);
        $persona = $this->persona();
        $inactiva = $this->persona();
        $inactiva->update(['status' => false]);

        $catalogos = $this->ajax('get', route('admin.mantenimientos.catalogos'))->assertOk()->json();
        $vehiculos = array_column($catalogos['vehiculos'], null, 'id');
        $personal = array_column($catalogos['personal'], null, 'id');

        $this->assertArrayHasKey($activo->id, $vehiculos);
        $this->assertArrayNotHasKey($inactivo->id, $vehiculos);
        $this->assertSame(['id', 'name', 'plate'], array_keys($vehiculos[$activo->id]));
        $this->assertSame($activo->name, $vehiculos[$activo->id]['name']);
        $this->assertSame($activo->plate, $vehiculos[$activo->id]['plate']);

        $this->assertArrayHasKey($persona->id, $personal);
        $this->assertArrayNotHasKey($inactiva->id, $personal);
        $this->assertSame(['id', 'name', 'last_name'], array_keys($personal[$persona->id]));
        $this->assertSame($persona->name, $personal[$persona->id]['name']);
        $this->assertSame($persona->last_name, $personal[$persona->id]['last_name']);
    }

    public function test_scope_bindings_devuelve_404_para_recursos_de_otro_padre(): void
    {
        $mantenimiento = $this->mantenimiento();
        $otro = $this->mantenimiento('2027-01-01', '2027-01-31');
        $horario = $this->servicio->crearHorario($mantenimiento, $this->datosHorario($this->vehiculo(), $this->persona()));
        $dia = $horario->dias()->first();
        $otroHorario = $this->servicio->crearHorario($otro, $this->datosHorario($this->vehiculo(), $this->persona()));

        $this->ajax('get', route('admin.mantenimientos.horarios.show', [$mantenimiento, $horario]))->assertOk();
        $this->ajax('get', route('admin.mantenimientos.horarios.show', [$otro, $horario]))->assertNotFound();
        $this->ajax('put', route('admin.mantenimientos.horarios.update', [$otro, $horario]), $this->datosHorario($this->vehiculo(), $this->persona()))->assertNotFound();
        $this->ajax('delete', route('admin.mantenimientos.horarios.destroy', [$otro, $horario]))->assertNotFound();

        $this->ajax('get', route('admin.mantenimientos.dias.show', [$mantenimiento, $horario, $dia]))->assertOk();
        $this->ajax('get', route('admin.mantenimientos.dias.show', [$mantenimiento, $otroHorario, $dia]))->assertNotFound();
        $this->ajax('put', route('admin.mantenimientos.dias.update', [$mantenimiento, $otroHorario, $dia]), ['realizado' => 1])->assertNotFound();
    }

    public function test_dias_update_acepta_multipart_y_reemplaza_o_quita_imagen_real(): void
    {
        $mantenimiento = $this->mantenimiento();
        $horario = $this->servicio->crearHorario($mantenimiento, $this->datosHorario($this->vehiculo(), $this->persona()));
        $dia = $horario->dias()->first();
        $vieja = $this->guardarArchivoReal();
        $dia->update(['image_path' => $vieja]);
        $fisicaVieja = public_path(ltrim($vieja, '/'));
        $fisicaNueva = null;
        $ruta = route('admin.mantenimientos.dias.guardar', [$mantenimiento, $horario, $dia]);

        try {
            $respuesta = $this->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])->post($ruta, [
                '_method' => 'PUT',
                'realizado' => 1,
                'image' => UploadedFile::fake()->image('nueva.jpg'),
            ])->assertOk();

            $nueva = $respuesta->json('dia.imagen');
            $fisicaNueva = public_path(ltrim($nueva, '/'));

            $this->assertStringStartsWith('/uploads/maintenance_days/', $nueva);
            $this->assertNotSame($vieja, $nueva);
            $this->assertFileDoesNotExist($fisicaVieja);
            $this->assertFileExists($fisicaNueva);

            $this->ajax('post', $ruta, [
                '_method' => 'PUT',
                'realizado' => 1,
                'quitar_imagen' => 1,
            ])->assertOk()->assertJsonPath('dia.imagen', asset('images/sin-imagen.svg'));

            $this->assertFileDoesNotExist($fisicaNueva);
            $this->assertNull($dia->fresh()->image_path);
        } finally {
            File::delete($fisicaVieja);

            if ($fisicaNueva !== null) {
                File::delete($fisicaNueva);
            }
        }
    }

    public function test_dias_update_ignora_quitar_imagen_cuando_llega_un_archivo(): void
    {
        $mantenimiento = $this->mantenimiento();
        $horario = $this->servicio->crearHorario($mantenimiento, $this->datosHorario($this->vehiculo(), $this->persona()));
        $dia = $horario->dias()->first();
        $vieja = $this->guardarArchivoReal();
        $dia->update(['image_path' => $vieja]);
        $fisicaVieja = public_path(ltrim($vieja, '/'));
        $fisicaNueva = null;
        $ruta = route('admin.mantenimientos.dias.guardar', [$mantenimiento, $horario, $dia]);

        try {
            $respuesta = $this->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])->post($ruta, [
                '_method' => 'PUT',
                'realizado' => 1,
                'quitar_imagen' => 1,
                'image' => UploadedFile::fake()->image('nueva.jpg'),
            ])->assertOk();

            $nueva = $respuesta->json('dia.imagen');
            $fisicaNueva = public_path(ltrim($nueva, '/'));

            $this->assertNotSame($vieja, $nueva);
            $this->assertFileDoesNotExist($fisicaVieja);
            $this->assertFileExists($fisicaNueva);
            $this->assertSame($nueva, $dia->fresh()->image_path);
        } finally {
            File::delete($fisicaVieja);

            if ($fisicaNueva !== null) {
                File::delete($fisicaNueva);
            }
        }
    }

    public function test_no_se_puede_eliminar_vehiculo_o_personal_con_horarios_y_si_sin_ellos(): void
    {
        $mantenimiento = $this->mantenimiento();
        $vehiculo = $this->vehiculo();
        $persona = $this->persona();
        $horario = $this->servicio->crearHorario($mantenimiento, $this->datosHorario($vehiculo, $persona));

        $this->deleteJson(route('admin.vehiculos.destroy', $vehiculo))
            ->assertStatus(422)->assertJsonPath('mensaje', 'No se puede eliminar: el vehículo figura en mantenimientos. Elimine primero esos horarios.');
        $this->deleteJson(route('admin.personal.destroy', $persona))
            ->assertStatus(422)->assertJsonPath('mensaje', 'No se puede eliminar: la persona figura como responsable de mantenimientos. Elimine primero esos horarios.');

        $this->assertDatabaseHas('vehicles', ['id' => $vehiculo->id]);
        $this->assertDatabaseHas('users', ['id' => $persona->id]);

        $this->servicio->eliminarHorario($horario);

        $this->deleteJson(route('admin.vehiculos.destroy', $vehiculo))->assertOk();
        $this->deleteJson(route('admin.personal.destroy', $persona))->assertOk();

        $this->assertDatabaseMissing('vehicles', ['id' => $vehiculo->id]);
        $this->assertDatabaseMissing('users', ['id' => $persona->id]);
    }

    public function test_alta_edicion_y_borrado_de_mantenimiento_por_ajax(): void
    {
        $respuesta = $this->ajax('post', route('admin.mantenimientos.store'), [
            'name' => 'Diciembre', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31',
        ])->assertOk();

        $id = $respuesta->json('mantenimiento.id');
        $this->assertDatabaseHas('maintenances', ['id' => $id, 'name' => 'Diciembre']);

        $this->ajax('put', route('admin.mantenimientos.update', $id), [
            'name' => 'Diciembre completo', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31',
        ])->assertOk()->assertJsonPath('mantenimiento.nombre', 'Diciembre completo');

        $this->ajax('delete', route('admin.mantenimientos.destroy', $id))->assertOk();
        $this->assertDatabaseMissing('maintenances', ['id' => $id]);
    }

    public function test_horarios_por_ajax_y_reglas_de_negocio_en_422(): void
    {
        $mantenimiento = $this->mantenimiento();
        $vehiculo = $this->vehiculo();
        $persona = $this->persona();
        $datos = $this->datosHorario($vehiculo, $persona);

        $respuesta = $this->ajax('post', route('admin.mantenimientos.horarios.store', $mantenimiento), $datos)->assertOk();
        $horarioId = $respuesta->json('horario.id');
        $this->assertDatabaseHas('maintenance_schedules', ['id' => $horarioId, 'vehicle_id' => $vehiculo->id]);
        $this->assertSame(4, MaintenanceDay::where('schedule_id', $horarioId)->count());
        $this->assertSame('08:00', $respuesta->json('horario.inicio'));
        $this->assertSame('10:00', $respuesta->json('horario.fin'));
        $this->ajax('get', route('admin.mantenimientos.show', $mantenimiento))
            ->assertOk()->assertJsonPath('horarios.0.inicio', '08:00')->assertJsonPath('horarios.0.fin', '10:00');

        $this->ajax('post', route('admin.mantenimientos.horarios.store', $mantenimiento), $datos)
            ->assertStatus(422)->assertJsonValidationErrors('start_time');

        $inactivo = $this->vehiculo(false);
        $this->ajax('post', route('admin.mantenimientos.horarios.store', $mantenimiento), $this->datosHorario($inactivo, $persona))
            ->assertStatus(422)->assertJsonValidationErrors('vehicle_id');

        $this->assertTrue(UsoEnMantenimiento::deVehiculo($vehiculo->id));
        $this->assertTrue(UsoEnMantenimiento::dePersona($persona->id));

        $this->ajax('put', route('admin.mantenimientos.horarios.update', [$mantenimiento, $horarioId]), array_merge($datos, ['weekday' => 2]))->assertOk();
        $this->assertSame(2, (int) $mantenimiento->schedules()->find($horarioId)->weekday);

        $this->ajax('delete', route('admin.mantenimientos.horarios.destroy', [$mantenimiento, $horarioId]))->assertOk();
        $this->assertDatabaseMissing('maintenance_schedules', ['id' => $horarioId]);
        $this->assertFalse(UsoEnMantenimiento::deVehiculo($vehiculo->id));
        $this->assertFalse(UsoEnMantenimiento::dePersona($persona->id));
    }

    public function test_el_dia_guarda_estado_observacion_e_imagen_segun_el_checkbox(): void
    {
        $mantenimiento = $this->mantenimiento();
        $horario = $this->servicio->crearHorario($mantenimiento, $this->datosHorario($this->vehiculo(), $this->persona()));
        [$primero, $segundo] = $horario->dias()->orderBy('date')->limit(2)->get()->all();

        $respuesta = $this->ajax('put', route('admin.mantenimientos.dias.update', [$mantenimiento, $horario, $primero]), [
            'realizado' => 1,
            'observation' => 'Cambiada la correa',
            'image' => UploadedFile::fake()->image('evidencia.jpg'),
        ])->assertOk()->assertJsonPath('dia.estado', MaintenanceDay::REALIZADO);

        $ruta = $respuesta->json('dia.imagen');
        $this->assertStringStartsWith('/uploads/maintenance_days/', $ruta);
        $fisica = public_path(ltrim($ruta, '/'));
        $this->assertFileExists($fisica);
        $this->assertDatabaseHas('maintenance_days', [
            'id' => $primero->id,
            'status' => MaintenanceDay::REALIZADO,
            'observation' => 'Cambiada la correa',
        ]);

        try {
            $this->ajax('put', route('admin.mantenimientos.dias.update', [$mantenimiento, $horario, $segundo]), [
                'realizado' => 0, 'observation' => 'Sin herramientas',
            ])->assertOk()->assertJsonPath('dia.estado', MaintenanceDay::NO_REALIZADO);

            $this->assertDatabaseHas('maintenance_days', [
                'id' => $segundo->id, 'status' => MaintenanceDay::NO_REALIZADO,
            ]);
        } finally {
            File::delete($fisica);
        }
    }

    public function test_horarios_devuelve_vista_o_json_segun_la_peticion(): void
    {
        $mantenimiento = $this->mantenimiento();
        $horario = $this->servicio->crearHorario($mantenimiento, $this->datosHorario($this->vehiculo(), $this->persona()));

        $this->get(route('admin.mantenimientos.horarios.index', $mantenimiento))
            ->assertOk()
            ->assertViewIs('admin.mantenimientos.horarios.index')
            ->assertSee('Horarios de mantenimiento')
            ->assertSee('Mantenimientos');

        $this->get(route('admin.mantenimientos.horarios.show', [$mantenimiento, $horario]))
            ->assertOk()
            ->assertViewIs('admin.mantenimientos.horarios.show')
            ->assertSee('Detalle del horario')
            ->assertSee('Horarios');

        $this->ajax('get', route('admin.mantenimientos.horarios.index', $mantenimiento))
            ->assertOk()->assertJsonPath('data.0.id', $horario->id);

        $this->ajax('get', route('admin.mantenimientos.horarios.show', [$mantenimiento, $horario]))
            ->assertOk()->assertJsonPath('horario.id', $horario->id);
    }

    public function test_permisos_y_menu_de_mantenimientos_estan_conectados(): void
    {
        $grupo = collect(config('adminlte.menu'))->first(fn ($item) => ($item['text'] ?? null) === 'Gestión de vehículos');

        $this->assertContains('maintenance.view', $grupo['can'] ?? []);
        $this->assertTrue(collect($grupo['submenu'] ?? [])->contains(
            fn ($item) => ($item['route'] ?? null) === 'admin.mantenimientos.index' && ($item['can'] ?? null) === 'maintenance.view'
        ));
        $this->assertSame('maintenance.view', CatalogoPermisos::paraRuta('admin.mantenimientos.index'));
        $this->assertSame('maintenance.create', CatalogoPermisos::paraRuta('admin.mantenimientos.horarios.store'));
        $this->assertSame('maintenance.edit', CatalogoPermisos::paraRuta('admin.mantenimientos.dias.guardar'));
        $this->assertSame('maintenance.delete', CatalogoPermisos::paraRuta('admin.mantenimientos.horarios.destroy'));

        $admin = User::factory()->create();
        $this->assertTrue($admin->esAdministrador());
        $this->assertTrue($admin->can('maintenance.view'));

        $flota = User::factory()->create(['role' => 'encargado_flota']);
        $flota->permisos()->create(['permission' => 'vehicles.view']);
        $migracion = require database_path('migrations/2026_11_20_110000_add_maintenance_permissions_to_existing_users.php');
        $migracion->up();

        foreach (['maintenance.view', 'maintenance.create', 'maintenance.edit', 'maintenance.delete'] as $permiso) {
            $this->assertDatabaseHas('user_permissions', ['user_id' => $flota->id, 'permission' => $permiso]);
        }

        $this->assertTrue($flota->fresh()->tienePermiso('maintenance.delete'));
    }

    public function test_no_hay_rutas_create_ni_edit_para_mantenimientos(): void
    {
        $this->assertFalse(Route::has('admin.mantenimientos.create'));
        $this->assertFalse(Route::has('admin.mantenimientos.edit'));
    }

    public function test_las_rutas_de_mantenimiento_exigen_permiso(): void
    {
        $this->mantenimiento();
        $operador = User::factory()->operador()->create();
        $this->actingAs($operador);

        $this->ajax('get', route('admin.mantenimientos.index'))->assertStatus(403);

        $operador->permisos()->create(['permission' => 'maintenance.view']);
        $this->actingAs($operador->fresh());
        $this->ajax('get', route('admin.mantenimientos.index'))->assertOk();
    }
}
