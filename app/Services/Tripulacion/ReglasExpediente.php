<?php

namespace App\Services\Tripulacion;

/**
 * Reglas de validación del alta/edición de tripulante con expediente.
 * Payload plano: identidad y expediente al mismo nivel; listas hijas como arrays anidados.
 */
class ReglasExpediente
{
    /** Campos raíz de cada paso del asistente (5 = revisión = todos). */
    private const PASOS = [
        1 => ['nombre', 'fecha_nacimiento', 'sexo', 'nacionalidad', 'agencia', 'rol', 'fecha_ingreso', 'estado_servicio'],
        2 => [
            'altura_m', 'peso_kg', 'grupo_sanguineo', 'evas',
            'fc_reposo', 'fc_maxima', 'presion_basal_sis', 'presion_basal_dia', 'spo2_basal',
            'temperatura_basal', 'glucosa_basal', 'vo2max', 'grasa_corporal_pct', 'densidad_osea_tscore',
            'horas_sueno_habituales', 'fumador', 'alcohol', 'actividad_fisica_semana_min',
            'dosis_radiacion_previa_msv', 'linea_base',
        ],
        3 => [
            'alergias', 'condiciones', 'medicamentos', 'antecedentes',
            'aptitud_vuelo', 'restricciones', 'ultimo_examen_en', 'notas',
        ],
        4 => ['contactos'],
    ];

    private static function lim(string $campo): array
    {
        return config("expediente.limites.$campo");
    }

    private static function rango(string $campo): string
    {
        [$min, $max] = self::lim($campo);

        return "min:$min|max:$max";
    }

    private static function en(string $catalogo, bool $claves = true): string
    {
        $c = config("expediente.$catalogo");

        return 'in:'.implode(',', $claves ? array_keys($c) : $c);
    }

    /**
     * @param  bool  $parcial  true = actualización: los campos obligatorios solo se exigen si vienen en el payload.
     * @return array<string, array<int, mixed>>
     */
    public static function reglas(bool $parcial = false): array
    {
        $marcadores = array_keys(config('indicadores.marcadores', []));
        [$edMin] = self::lim('edad');
        [, $edMax] = self::lim('edad');
        $maxItems = config('expediente.max_items');

        $nacMax = now()->subYears($edMin)->toDateString();
        $nacMin = now()->subYears($edMax + 1)->toDateString();

        $num = fn (string $c, string $tipo = 'numeric') => ['nullable', $tipo, ...explode('|', self::rango($c))];

        $reglas = [
            // 1. Identidad
            'nombre' => ['required', 'string', 'max:120'],
            'fecha_nacimiento' => ['required', 'date', 'before_or_equal:'.$nacMax, 'after:'.$nacMin],
            'sexo' => ['required', self::en('sexos')],
            'nacionalidad' => ['nullable', 'string', 'max:80'],
            'agencia' => ['nullable', 'string', 'max:80'],
            'rol' => ['nullable', 'string', 'max:80'],
            'fecha_ingreso' => ['nullable', 'date', 'before_or_equal:today'],
            'estado_servicio' => ['nullable', self::en('estados_servicio')],

            // 2. Biometría y línea base
            'altura_m' => $num('altura_m'),
            'peso_kg' => $num('peso_kg'),
            'grupo_sanguineo' => ['nullable', self::en('grupos_sanguineos', false)],
            'evas' => $num('evas', 'integer'),
            'fc_reposo' => $num('fc_reposo', 'integer'),
            'fc_maxima' => $num('fc_maxima', 'integer'),
            'presion_basal_sis' => $num('presion_basal_sis', 'integer'),
            'presion_basal_dia' => $num('presion_basal_dia', 'integer'),
            'spo2_basal' => $num('spo2_basal', 'integer'),
            'temperatura_basal' => $num('temperatura_basal'),
            'glucosa_basal' => $num('glucosa_basal', 'integer'),
            'vo2max' => $num('vo2max'),
            'grasa_corporal_pct' => $num('grasa_corporal_pct'),
            'densidad_osea_tscore' => $num('densidad_osea_tscore'),
            'horas_sueno_habituales' => $num('horas_sueno_habituales'),
            'fumador' => ['nullable', 'boolean'],
            'alcohol' => ['nullable', self::en('alcohol')],
            'actividad_fisica_semana_min' => $num('actividad_fisica_semana_min', 'integer'),
            'dosis_radiacion_previa_msv' => $num('dosis_radiacion_previa_msv'),
            'linea_base' => ['nullable', 'array', function ($attr, $valor, $fail) use ($marcadores) {
                foreach (array_keys((array) $valor) as $clave) {
                    if (! in_array($clave, $marcadores, true)) {
                        $fail("La línea base incluye un marcador desconocido: «{$clave}».");
                    }
                }
            }],
            'linea_base.*' => ['nullable', 'numeric', ...explode('|', self::rango('linea_base'))],

            // 3. Historia clínica
            'alergias' => ['nullable', 'array', 'max:'.$maxItems['alergias']],
            'alergias.*.sustancia' => ['required', 'string', 'max:120'],
            'alergias.*.reaccion' => ['nullable', 'string', 'max:160'],
            'alergias.*.gravedad' => ['required', self::en('gravedades')],

            'condiciones' => ['nullable', 'array', 'max:'.$maxItems['condiciones']],
            'condiciones.*.nombre' => ['required', 'string', 'max:120'],
            'condiciones.*.estado' => ['required', self::en('estados_condicion')],
            'condiciones.*.desde' => ['nullable', 'date', 'before_or_equal:today'],
            'condiciones.*.notas' => ['nullable', 'string', 'max:500'],

            'medicamentos' => ['nullable', 'array', 'max:'.$maxItems['medicamentos']],
            'medicamentos.*.nombre' => ['required', 'string', 'max:120'],
            'medicamentos.*.dosis_texto' => ['nullable', 'string', 'max:120'],
            'medicamentos.*.frecuencia' => ['nullable', 'string', 'max:120'],
            'medicamentos.*.motivo' => ['nullable', 'string', 'max:160'],
            'medicamentos.*.desde' => ['nullable', 'date', 'before_or_equal:today'],
            'medicamentos.*.activo' => ['nullable', 'boolean'],

            'antecedentes' => ['nullable', 'array', 'max:'.$maxItems['antecedentes']],
            'antecedentes.*.parentesco' => ['required', self::en('parentescos')],
            'antecedentes.*.condicion' => ['required', 'string', 'max:160'],
            'antecedentes.*.edad_aparicion' => $num('edad_aparicion', 'integer'),

            'aptitud_vuelo' => ['nullable', self::en('aptitudes')],
            'restricciones' => ['nullable', 'string', 'max:1000'],
            'ultimo_examen_en' => ['nullable', 'date', 'before_or_equal:today'],
            'notas' => ['nullable', 'string', 'max:2000'],

            // 4. Contactos de emergencia
            'contactos' => ['nullable', 'array', 'max:'.$maxItems['contactos'], function ($attr, $valor, $fail) {
                if (is_array($valor) && $valor !== [] && ! collect($valor)->contains(fn ($c) => ! empty($c['principal']))) {
                    $fail('Marca al menos un contacto de emergencia como principal.');
                }
            }],
            'contactos.*.nombre' => ['required', 'string', 'max:120'],
            'contactos.*.parentesco' => ['required', self::en('parentescos')],
            'contactos.*.telefono' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\-\s.]{6,32}$/'],
            'contactos.*.principal' => ['nullable', 'boolean'],
        ];

        if ($parcial) {
            foreach ($reglas as $campo => $r) {
                if (! str_contains($campo, '.')) {
                    array_unshift($reglas[$campo], 'sometimes');
                }
            }
        }

        return $reglas;
    }

    /** Subconjunto de reglas para un paso del asistente (1-4); 5 (revisión) o cualquier otro = todas. */
    public static function reglasPaso(int $paso): array
    {
        $reglas = self::reglas();
        if (! isset(self::PASOS[$paso])) {
            return $reglas;
        }

        return array_filter(
            $reglas,
            fn ($_, $campo) => in_array(explode('.', $campo)[0], self::PASOS[$paso], true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** Campos raíz que pertenecen a un paso (útil para la UI). */
    public static function camposPaso(int $paso): array
    {
        return self::PASOS[$paso] ?? array_merge(...array_values(self::PASOS));
    }

    public static function mensajes(): array
    {
        [$aMin, $aMax] = config('expediente.limites.altura_m');
        [$pMin, $pMax] = config('expediente.limites.peso_kg');
        [$eMin, $eMax] = config('expediente.limites.edad');

        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max.string' => 'El campo :attribute no puede superar :max caracteres.',
            'max.array' => 'No puedes registrar más de :max elementos en :attribute.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'min.numeric' => 'El campo :attribute debe ser como mínimo :min.',
            'max.numeric' => 'El campo :attribute debe ser como máximo :max.',
            'date' => 'El campo :attribute no es una fecha válida.',
            'boolean' => 'El campo :attribute debe ser sí o no.',
            'array' => 'El campo :attribute tiene un formato no válido.',
            'in' => 'El valor elegido en :attribute no es válido.',
            'before_or_equal' => 'La fecha de :attribute no puede ser futura.',
            'fecha_nacimiento.before_or_equal' => "El tripulante debe tener al menos {$eMin} años.",
            'fecha_nacimiento.after' => "El tripulante no puede tener más de {$eMax} años.",
            'altura_m.min' => "La altura debe estar entre {$aMin} y {$aMax} m.",
            'altura_m.max' => "La altura debe estar entre {$aMin} y {$aMax} m.",
            'peso_kg.min' => "El peso debe estar entre {$pMin} y {$pMax} kg.",
            'peso_kg.max' => "El peso debe estar entre {$pMin} y {$pMax} kg.",
            'contactos.*.telefono.regex' => 'El teléfono del contacto solo puede incluir dígitos, espacios, +, -, ( y ).',
            'linea_base.*.numeric' => 'El valor de línea base de :attribute debe ser numérico.',
        ];
    }

    public static function atributos(): array
    {
        return [
            'nombre' => 'nombre', 'fecha_nacimiento' => 'fecha de nacimiento', 'sexo' => 'sexo',
            'nacionalidad' => 'nacionalidad', 'agencia' => 'agencia', 'rol' => 'rol',
            'fecha_ingreso' => 'fecha de ingreso', 'estado_servicio' => 'estado de servicio',
            'altura_m' => 'altura', 'peso_kg' => 'peso', 'grupo_sanguineo' => 'grupo sanguíneo', 'evas' => 'EVAs',
            'fc_reposo' => 'frecuencia cardíaca en reposo', 'fc_maxima' => 'frecuencia cardíaca máxima',
            'presion_basal_sis' => 'presión sistólica basal', 'presion_basal_dia' => 'presión diastólica basal',
            'spo2_basal' => 'saturación de O₂ basal', 'temperatura_basal' => 'temperatura basal',
            'glucosa_basal' => 'glucosa basal', 'vo2max' => 'VO₂máx', 'grasa_corporal_pct' => 'grasa corporal',
            'densidad_osea_tscore' => 'T-score de densidad ósea', 'horas_sueno_habituales' => 'horas de sueño habituales',
            'fumador' => 'fumador', 'alcohol' => 'consumo de alcohol',
            'actividad_fisica_semana_min' => 'actividad física semanal',
            'dosis_radiacion_previa_msv' => 'dosis de radiación previa',
            'linea_base' => 'línea base', 'linea_base.*' => 'marcador de línea base',
            'alergias' => 'alergias', 'alergias.*.sustancia' => 'sustancia de la alergia',
            'alergias.*.reaccion' => 'reacción de la alergia', 'alergias.*.gravedad' => 'gravedad de la alergia',
            'condiciones' => 'condiciones médicas', 'condiciones.*.nombre' => 'nombre de la condición',
            'condiciones.*.estado' => 'estado de la condición', 'condiciones.*.desde' => 'fecha de inicio de la condición',
            'condiciones.*.notas' => 'notas de la condición',
            'medicamentos' => 'medicamentos', 'medicamentos.*.nombre' => 'nombre del medicamento',
            'medicamentos.*.dosis_texto' => 'dosis del medicamento', 'medicamentos.*.frecuencia' => 'frecuencia del medicamento',
            'medicamentos.*.motivo' => 'motivo del medicamento', 'medicamentos.*.desde' => 'fecha de inicio del medicamento',
            'medicamentos.*.activo' => 'medicamento activo',
            'antecedentes' => 'antecedentes familiares', 'antecedentes.*.parentesco' => 'parentesco del antecedente',
            'antecedentes.*.condicion' => 'condición del antecedente', 'antecedentes.*.edad_aparicion' => 'edad de aparición',
            'aptitud_vuelo' => 'aptitud de vuelo', 'restricciones' => 'restricciones',
            'ultimo_examen_en' => 'fecha del último examen', 'notas' => 'notas',
            'contactos' => 'contactos de emergencia', 'contactos.*.nombre' => 'nombre del contacto',
            'contactos.*.parentesco' => 'parentesco del contacto', 'contactos.*.telefono' => 'teléfono del contacto',
            'contactos.*.principal' => 'contacto principal',
        ];
    }
}
