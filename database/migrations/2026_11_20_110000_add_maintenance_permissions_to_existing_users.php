<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISOS_POR_ROL = [
        'encargado_flota' => ['maintenance.view', 'maintenance.create', 'maintenance.edit', 'maintenance.delete'],
        'jefe_operaciones' => ['maintenance.view'],
        'consulta' => ['maintenance.view'],
    ];

    public function up(): void
    {
        $filas = [];
        $ahora = now();

        foreach (self::PERMISOS_POR_ROL as $rol => $permisos) {
            $usuarios = DB::table('users')
                ->where('role', $rol)
                ->whereNull('usertype_id')
                ->pluck('id');

            foreach ($usuarios as $id) {
                foreach ($permisos as $permiso) {
                    $filas[] = [
                        'user_id' => $id,
                        'permission' => $permiso,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }
        }

        if ($filas !== []) {
            DB::table('user_permissions')->insertOrIgnore($filas);
        }
    }

    public function down(): void
    {
        $usuarios = DB::table('users')
            ->whereIn('role', array_keys(self::PERMISOS_POR_ROL))
            ->whereNull('usertype_id')
            ->pluck('id');

        DB::table('user_permissions')
            ->whereIn('user_id', $usuarios)
            ->where('permission', 'like', 'maintenance.%')
            ->delete();
    }
};
