<?php

namespace Database\Seeders;

use App\Models\BotiquinItem;
use Illuminate\Database\Seeder;

/**
 * Inventario informativo: solo grupos y nombres de la guía. Stock y caducidad son VALORES DE EJEMPLO.
 * Idempotente: crea lo que falta y no pisa lo que alguien ya editó.
 */
class BotiquinSeeder extends Seeder
{
    public function run(): void
    {
        $i = 0;
        foreach (BotiquinItem::GRUPOS as $grupo => $nombres) {
            foreach ($nombres as $nombre) {
                BotiquinItem::firstOrCreate(
                    ['nombre' => $nombre],
                    [
                        'grupo' => $grupo,
                        'stock' => 10 + ($i % 5) * 5,
                        'unidad' => $nombre === 'oxígeno al 100 %' ? 'cilindros' : 'unidades',
                        'caducidad' => now()->startOfDay()->addDays(30 + ($i % 6) * 120),
                        'contraindicaciones' => BotiquinItem::PENDIENTE,
                        'valor_ejemplo' => true,
                    ]
                );
                $i++;
            }
        }
    }
}
