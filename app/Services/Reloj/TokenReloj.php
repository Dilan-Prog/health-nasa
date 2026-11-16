<?php

namespace App\Services\Reloj;

use App\Models\Astronauta;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/** Emisión y rotación del token del reloj. Solo guarda hashes (token) y el secreto cifrado (firma). */
class TokenReloj
{
    /**
     * Genera un token nuevo y su secreto de firma. El token vigente pasa a "previo" con una gracia.
     *
     * @return array{token:string, secreto:string, vence_en:\Illuminate\Support\Carbon, gracia_hasta:?\Illuminate\Support\Carbon}
     */
    public function emitir(Astronauta $a, ?int $dias = null): array
    {
        $dias ??= (int) config('mision.reloj_token_dias', 30);
        $token = Str::random(40);
        $secreto = Str::random(48);
        $vence = now()->addDays($dias);
        $gracia = null;

        $cambios = [
            'token_dispositivo' => hash('sha256', $token),
            'token_vence_en' => $vence,
            'secreto_firma' => Crypt::encryptString($secreto),
        ];
        if ($a->token_dispositivo) {
            $gracia = now()->addMinutes((int) config('mision.reloj_gracia_minutos', 60));
            $cambios += [
                'token_previo' => $a->token_dispositivo,
                'token_previo_vence_en' => $gracia,
                'secreto_firma_previo' => $a->secreto_firma,
            ];
        }
        $a->forceFill($cambios)->save();

        return ['token' => $token, 'secreto' => $secreto, 'vence_en' => $vence, 'gracia_hasta' => $gracia];
    }
}
