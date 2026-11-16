<?php

namespace App\Services\Tripulacion;

use App\Models\Astronauta;
use App\Models\ExpedienteMedico;
use App\Models\Mision;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AltaTripulante
{
    private const IDENTIDAD = ['nombre', 'fecha_nacimiento', 'sexo', 'nacionalidad', 'agencia', 'rol', 'fecha_ingreso',
        'estado_servicio', 'altura_m', 'peso_kg', 'grupo_sanguineo', 'evas'];

    /** Listas hijas: clave del payload => [relación, columnas permitidas]. */
    private const HIJOS = [
        'alergias' => ['alergias', ['sustancia', 'reaccion', 'gravedad']],
        'condiciones' => ['condiciones', ['nombre', 'estado', 'desde', 'notas']],
        'medicamentos' => ['medicamentos', ['nombre', 'dosis_texto', 'frecuencia', 'motivo', 'desde', 'activo']],
        'antecedentes' => ['antecedentes', ['parentesco', 'condicion', 'edad_aparicion']],
        'contactos' => ['contactos', ['nombre', 'parentesco', 'telefono', 'principal']],
    ];

    private const RELACIONES = ['expediente', 'alergias', 'condiciones', 'medicamentos', 'antecedentes', 'contactos'];

    /** Tripulantes máximos por misión (config/expediente.php). */
    public static function maximo(): int
    {
        return (int) config('expediente.max_tripulantes', 5);
    }

    public static function cupoLleno(Mision $mision): bool
    {
        return Astronauta::query()->where('mision_id', $mision->id)->count() >= self::maximo();
    }

    /**
     * Da de alta un tripulante con su expediente en una transacción.
     * El token del reloj queda guardado como hash; el valor en claro solo está en $astronauta->tokenPlano.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function registrar(array $datos, Mision $mision): Astronauta
    {
        $datos = $this->validar($datos);
        $token = Str::random(40);

        $id = DB::transaction(function () use ($datos, $mision, $token) {
            // Con el cupo lleno no se admite otro tripulante (se vuelve a comprobar dentro de la transacción).
            if (Astronauta::query()->where('mision_id', $mision->id)->lockForUpdate()->count() >= self::maximo()) {
                throw ValidationException::withMessages([
                    'nombre' => 'La misión ya tiene el máximo de '.self::maximo().' tripulantes.',
                ]);
            }
            $a = new Astronauta(Arr::only($datos, self::IDENTIDAD));
            $a->mision_id = $mision->id;
            $a->mision = $mision->nombre;
            $a->entorno = $mision->entorno;
            $a->estado_servicio = $datos['estado_servicio'] ?? 'activo';
            $a->evas = $datos['evas'] ?? 0;
            $a->edad = $this->edadDe($datos['fecha_nacimiento']);
            $a->token_dispositivo = hash('sha256', $token);
            $a->save();

            $this->guardarExpediente($a, $datos);
            foreach (array_keys(self::HIJOS) as $clave) {
                $this->reemplazarHijos($a, $clave, $datos[$clave] ?? []);
            }

            return $a->id;
        });

        $fresco = $this->cargar($id);
        $fresco->tokenPlano = $token;

        return $fresco;
    }

    /**
     * Actualiza identidad y expediente con los campos presentes en $datos (los obligatorios solo se exigen si vienen).
     * Cada lista hija presente en $datos se REEMPLAZA completa; las ausentes no se tocan.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function actualizar(Astronauta $a, array $datos): Astronauta
    {
        $datos = $this->validar($datos, true);

        DB::transaction(function () use ($a, $datos) {
            $identidad = Arr::only($datos, self::IDENTIDAD);
            if (array_key_exists('estado_servicio', $identidad) && $identidad['estado_servicio'] === null) {
                unset($identidad['estado_servicio']);
            }
            if (isset($identidad['fecha_nacimiento'])) {
                $identidad['edad'] = $this->edadDe($identidad['fecha_nacimiento']);
            }
            if ($identidad !== []) {
                $a->update($identidad);
            }

            $this->guardarExpediente($a, $datos);
            foreach (array_keys(self::HIJOS) as $clave) {
                if (array_key_exists($clave, $datos)) {
                    $this->reemplazarHijos($a, $clave, $datos[$clave] ?? []);
                }
            }
        });

        return $this->cargar($a->id);
    }

    /** @return array{porcentaje:int, faltantes:array<int,string>} */
    public function completitud(Astronauta $a): array
    {
        $a->loadMissing(self::RELACIONES);
        $e = $a->expediente;

        $criterios = [
            'Fecha de nacimiento' => (bool) $a->fecha_nacimiento,
            'Nacionalidad y agencia' => filled($a->nacionalidad) && filled($a->agencia),
            'Altura y peso' => $a->altura_m && $a->peso_kg,
            'Grupo sanguíneo' => filled($a->grupo_sanguineo),
            'Línea base personal' => $e && ! empty($e->linea_base),
            'Signos basales (frecuencia cardíaca y presión)' => $e && $e->fc_reposo && $e->presion_basal_sis && $e->presion_basal_dia,
            'Hábitos (sueño y actividad física)' => $e && $e->horas_sueno_habituales !== null && $e->actividad_fisica_semana_min !== null,
            'Aptitud de vuelo evaluada' => $e && $e->aptitud_vuelo !== 'pendiente',
            'Fecha del último examen médico' => $e && $e->ultimo_examen_en,
            'Contacto de emergencia principal' => $a->contactos->contains('principal', true),
        ];

        $faltantes = array_keys(array_filter($criterios, fn ($ok) => ! $ok));
        $total = count($criterios);

        return [
            'porcentaje' => (int) round(100 * ($total - count($faltantes)) / $total),
            'faltantes' => $faltantes,
        ];
    }

    private function validar(array $datos, bool $parcial = false): array
    {
        $v = Validator::make($datos, ReglasExpediente::reglas($parcial), ReglasExpediente::mensajes(), ReglasExpediente::atributos());
        $v->validate();

        $limpios = $v->validated();
        if (isset($limpios['linea_base'])) {
            $limpios['linea_base'] = array_map(
                fn ($x) => $x + 0,
                array_filter($limpios['linea_base'], fn ($x) => $x !== null && $x !== '')
            );
        }

        return $limpios;
    }

    private function edadDe($fecha): int
    {
        return (int) \Illuminate\Support\Carbon::parse($fecha)->diffInYears(now());
    }

    private function guardarExpediente(Astronauta $a, array $datos): void
    {
        $campos = array_diff((new ExpedienteMedico)->getFillable(), ['astronauta_id']);
        $valores = Arr::only($datos, $campos);

        if (array_key_exists('linea_base', $valores) && $valores['linea_base'] === []) {
            $valores['linea_base'] = null;
        }
        // Un null explícito en un campo con default de BD usaría NULL; se omite para conservar el default.
        foreach (['aptitud_vuelo', 'fumador', 'dosis_radiacion_previa_msv'] as $conDefault) {
            if (array_key_exists($conDefault, $valores) && $valores[$conDefault] === null) {
                unset($valores[$conDefault]);
            }
        }

        if ($a->expediente()->exists()) {
            $a->expediente()->update($valores);
        } else {
            $a->expediente()->create($valores);
        }
    }

    private function reemplazarHijos(Astronauta $a, string $clave, array $filas): void
    {
        [$relacion, $columnas] = self::HIJOS[$clave];
        $a->{$relacion}()->delete();
        foreach (array_values($filas) as $fila) {
            $a->{$relacion}()->create(Arr::only($fila, $columnas));
        }
    }

    private function cargar(int $id): Astronauta
    {
        return Astronauta::with(self::RELACIONES)->findOrFail($id);
    }
}
