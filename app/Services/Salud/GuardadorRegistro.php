<?php

namespace App\Services\Salud;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\RegistroSalud;
use App\Models\ZonaDolor;
use App\Support\MusculosCuerpo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guarda de una vez un registro de la pantalla Registrar: mediciones (por ProcesadorMediciones, con su
 * evaluación y alertas habituales), zonas de dolor, síntomas, nota y origen. Todo o nada, en una transacción.
 * Si hay un valor crítico o dolor >= 8 encola UN aviso a tierra del registro (con las zonas en la carga).
 */
class GuardadorRegistro
{
    public function __construct(private ProcesadorMediciones $procesador)
    {
    }

    public static function make(): self
    {
        return new self(ProcesadorMediciones::make());
    }

    /**
     * @param array<string, array> $mediciones clave de indicador => valores (['valor'=>…] o ['sistolica'=>…,'diastolica'=>…])
     * @param array<string, array{int: int, tipo: string, desde: string}> $zonas id de músculo => datos
     * @param string[] $sintomas
     * @return array{registro: RegistroSalud, estados: array<string, string>, aviso: bool}
     *
     * @throws ValidationException (clave de indicador => mensaje) si una medición es duplicada o inválida; no se guarda nada
     */
    public function guardar(Astronauta $astronauta, array $mediciones, array $zonas, array $sintomas, ?string $nota, string $origen): array
    {
        $origen = array_key_exists($origen, RegistroSalud::ORIGENES) ? $origen : 'manual';
        $sintomas = array_values(array_intersect(RegistroSalud::SINTOMAS, $sintomas));
        $nota = $nota !== null && trim($nota) !== '' ? trim($nota) : null;
        $zonas = array_filter($zonas, fn ($z, $id) => in_array($id, MusculosCuerpo::ids(), true), ARRAY_FILTER_USE_BOTH);

        return DB::transaction(function () use ($astronauta, $mediciones, $zonas, $sintomas, $nota, $origen) {
            $instante = now();
            $estados = [];
            foreach ($mediciones as $clave => $valores) {
                try {
                    $estados[$clave] = $this->procesador->registrar($astronauta, $clave, $valores, $origen, $instante, emitir: false)->estado;
                } catch (ValidationException $e) {
                    // Se devuelve el error asociado al indicador para mostrarlo en su tarjeta.
                    throw ValidationException::withMessages([$clave => collect($e->errors())->flatten()->first()]);
                }
            }

            $dolorAlto = collect($zonas)->contains(fn ($z) => (int) $z['int'] >= ZonaDolor::INTENSIDAD_ALERTA);
            $critico = in_array('critico', $estados, true);
            $aviso = $critico || $dolorAlto;

            $registro = RegistroSalud::create([
                'astronauta_id' => $astronauta->id,
                'origen' => $origen,
                'sintomas' => $sintomas ?: null,
                'nota' => $nota,
                'aviso_tierra' => $aviso,
                'registrado_en' => $instante,
            ]);

            $carga = [];
            foreach ($zonas as $id => $z) {
                $zona = $registro->zonas()->create([
                    'astronauta_id' => $astronauta->id,
                    'musculo' => $id,
                    'intensidad' => max(1, min(10, (int) $z['int'])),
                    'tipo' => in_array($z['tipo'] ?? '', ZonaDolor::TIPOS, true) ? $z['tipo'] : ZonaDolor::TIPOS[0],
                    'desde' => in_array($z['desde'] ?? '', ZonaDolor::DESDE, true) ? $z['desde'] : ZonaDolor::DESDE[0],
                ]);
                $carga[] = ['musculo' => $id, 'zona' => ZonaDolor::nombre($id), 'intensidad' => $zona->intensidad, 'tipo' => $zona->tipo, 'desde' => $zona->desde];
            }

            if ($aviso) {
                AlertaSaliente::create([
                    'evaluacion_id' => null,
                    'astronauta_id' => $astronauta->id,
                    'carga' => [
                        'astronauta' => $astronauta->nombre,
                        'indicador' => 'Health log',
                        'valores' => $mediciones ?: null,
                        'estado' => 'critico',
                        'accion' => ReglasEscalamiento::CONTACTAR_TIERRA,
                        'origen' => 'registro',
                        'medido_en' => $instante->toIso8601String(),
                        'zonas_dolor' => $carga,
                        'sintomas' => $sintomas,
                        'nota' => $nota,
                    ],
                ]);
            }

            return ['registro' => $registro, 'estados' => $estados, 'aviso' => $aviso];
        });
    }
}
