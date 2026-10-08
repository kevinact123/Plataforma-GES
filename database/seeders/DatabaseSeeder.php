<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = (string) env('ADMIN_PASSWORD', '');

        if ($adminPassword === '') {
            throw new \RuntimeException('Define ADMIN_PASSWORD en .env antes de ejecutar el seeder.');
        }

        Rol::query()->updateOrCreate(
            ['id_rol' => 1],
            ['nombre' => 'Administrador', 'descripcion' => 'Administración completa del sistema'],
        );

        User::query()->updateOrCreate(
            ['username' => env('ADMIN_USERNAME', 'admin')],
            [
                'nombre' => env('ADMIN_NAME', 'Administrador'),
                'apellido' => env('ADMIN_LASTNAME', 'GES'),
                'password' => $adminPassword,
                'id_rol' => 1,
                'activo' => true,
            ],
        );

        Rol::query()->updateOrCreate(
            ['id_rol' => 2],
            ['nombre' => 'Supervisor', 'descripcion' => 'Supervisión y distribución de carga laboral'],
        );
        $digitadoraRol = Rol::query()->updateOrCreate(
            ['id_rol' => 3],
            ['nombre' => 'Digitadora', 'descripcion' => 'Digitación y gestión de registros GES'],
        );

        $digitadoraPassword = (string) env('DIGITADORA_PASSWORD', '');

        if ($digitadoraPassword !== '') {
            User::query()->updateOrCreate(
                ['username' => env('DIGITADORA_USERNAME', 'digitadora01')],
                [
                    'nombre' => env('DIGITADORA_NAME', 'Digitadora'),
                    'apellido' => env('DIGITADORA_LASTNAME', 'GES'),
                    'password' => $digitadoraPassword,
                    'id_rol' => $digitadoraRol->id_rol,
                    'tipo_digitadora' => User::TIPO_DIGITADORA_NO_CONFIDENCIAL,
                    'activo' => true,
                ],
            );
        }
    }
}
