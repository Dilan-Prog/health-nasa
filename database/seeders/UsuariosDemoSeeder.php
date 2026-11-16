<?php

namespace Database\Seeders;

use App\Models\Astronauta;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tres usuarios de demostración (uno por rol). Las contraseñas se leen de DEMO_PASSWORD_TRIPULANTE,
 * DEMO_PASSWORD_CMO y DEMO_PASSWORD_FLIGHT_SURGEON; si falta alguna no se crea ese usuario y se avisa.
 */
class UsuariosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $astronautas = Astronauta::query()->orderBy('id')->get();
        $primero = $astronautas->first();
        $cmo = $primero ? $astronautas->first(fn ($a) => $a->id !== $primero->id && $a->mision_id === $primero->mision_id) : null;

        $defs = [
            [User::ROL_TRIPULANTE, 'DEMO_PASSWORD_TRIPULANTE', 'Tripulante demo', 'tripulante@demo.test', $primero],
            [User::ROL_CMO, 'DEMO_PASSWORD_CMO', 'CMO demo', 'cmo@demo.test', $cmo],
            [User::ROL_FLIGHT_SURGEON, 'DEMO_PASSWORD_FLIGHT_SURGEON', 'Flight surgeon demo', 'flight-surgeon@demo.test', null],
        ];

        foreach ($defs as [$rol, $var, $nombre, $correo, $astronauta]) {
            $clave = env($var);
            if (! $clave) {
                $this->command?->warn("Falta {$var} en el .env: no se crea el usuario {$rol}.");

                continue;
            }
            $usuario = User::query()->updateOrCreate(['email' => $correo], ['name' => $nombre, 'rol' => $rol, 'password' => $clave]);
            if ($astronauta) {
                $astronauta->forceFill(['user_id' => $usuario->id])->save();
            }
        }
    }
}
