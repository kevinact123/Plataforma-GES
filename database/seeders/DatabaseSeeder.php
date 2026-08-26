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

        $rol = Rol::query()->firstOrCreate(
            ['nombre' => 'admin'],
            ['descripcion' => 'Administrador del sistema'],
        );

        User::query()->updateOrCreate(
            ['username' => env('ADMIN_USERNAME', 'admin')],
            [
                'nombre' => env('ADMIN_NAME', 'Administrador'),
                'apellido' => env('ADMIN_LASTNAME', 'GES'),
                'password' => $adminPassword,
                'id_rol' => $rol->id_rol,
                'activo' => true,
            ],
        );

        $digitadoraPassword = (string) env('DIGITADORA_PASSWORD', '');

        if ($digitadoraPassword !== '') {
            $digitadoraRol = Rol::query()->firstOrCreate(
                ['nombre' => 'digitadora'],
                ['descripcion' => 'Digitadora'],
            );

            User::query()->updateOrCreate(
                ['username' => env('DIGITADORA_USERNAME', 'digitadora01')],
                [
                    'nombre' => env('DIGITADORA_NAME', 'Digitadora'),
                    'apellido' => env('DIGITADORA_LASTNAME', 'GES'),
                    'password' => $digitadoraPassword,
                    'id_rol' => $digitadoraRol->id_rol,
                    'activo' => true,
                ],
            );
        }
    }
}
