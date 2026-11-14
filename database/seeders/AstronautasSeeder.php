<?php

namespace Database\Seeders;

use App\Models\Astronauta;
use Illuminate\Database\Seeder;

class AstronautasSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['nombre' => 'Cdte. Elena Vargas', 'mision' => 'Estación Orbital', 'entorno' => 'estacion'],
            ['nombre' => 'Ing. Marco Reyes', 'mision' => 'Tránsito a la Luna', 'entorno' => 'nave'],
            ['nombre' => 'Dra. Ana Solís', 'mision' => 'Base Marciana Alfa', 'entorno' => 'base_marciana'],
        ] as $a) {
            Astronauta::firstOrCreate(['nombre' => $a['nombre']], $a);
        }
    }
}
