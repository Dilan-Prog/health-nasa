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
                        $fail("The personal baseline includes an unknown marker: \"{$clave}\".");
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
                    $fail('Mark at least one emergency contact as primary.');
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
            'required' => 'The :attribute field is required.',
            'string' => 'The :attribute field must be text.',
            'max.string' => 'The :attribute field may not exceed :max characters.',
            'max.array' => 'You cannot enter more than :max items in :attribute.',
            'numeric' => 'The :attribute field must be a number.',
            'integer' => 'The :attribute field must be a whole number.',
            'min.numeric' => 'The :attribute field must be at least :min.',
            'max.numeric' => 'The :attribute field must be at most :max.',
            'date' => 'The :attribute field is not a valid date.',
            'boolean' => 'The :attribute field must be yes or no.',
            'array' => 'The :attribute field has an invalid format.',
            'in' => 'The value selected for :attribute is not valid.',
            'before_or_equal' => 'The date for :attribute cannot be in the future.',
            'fecha_nacimiento.before_or_equal' => "The crew member must be at least {$eMin} years old.",
            'fecha_nacimiento.after' => "The crew member cannot be older than {$eMax} years.",
            'altura_m.min' => "Height must be between {$aMin} and {$aMax} m.",
            'altura_m.max' => "Height must be between {$aMin} and {$aMax} m.",
            'peso_kg.min' => "Weight must be between {$pMin} and {$pMax} kg.",
            'peso_kg.max' => "Weight must be between {$pMin} and {$pMax} kg.",
            'contactos.*.telefono.regex' => 'The contact phone number may only include digits, spaces, +, -, ( and ).',
            'linea_base.*.numeric' => 'The personal baseline value for :attribute must be numeric.',
        ];
    }

    public static function atributos(): array
    {
        return [
            'nombre' => 'name', 'fecha_nacimiento' => 'date of birth', 'sexo' => 'sex',
            'nacionalidad' => 'nationality', 'agencia' => 'agency', 'rol' => 'role',
            'fecha_ingreso' => 'enlistment date', 'estado_servicio' => 'service status',
            'altura_m' => 'height', 'peso_kg' => 'weight', 'grupo_sanguineo' => 'blood type', 'evas' => 'EVAs',
            'fc_reposo' => 'resting heart rate', 'fc_maxima' => 'maximum heart rate',
            'presion_basal_sis' => 'baseline systolic pressure', 'presion_basal_dia' => 'baseline diastolic pressure',
            'spo2_basal' => 'baseline SpO₂', 'temperatura_basal' => 'baseline temperature',
            'glucosa_basal' => 'baseline glucose', 'vo2max' => 'VO₂max', 'grasa_corporal_pct' => 'body fat',
            'densidad_osea_tscore' => 'bone density T-score', 'horas_sueno_habituales' => 'usual sleep hours',
            'fumador' => 'smoker', 'alcohol' => 'alcohol use',
            'actividad_fisica_semana_min' => 'weekly physical activity',
            'dosis_radiacion_previa_msv' => 'prior radiation dose',
            'linea_base' => 'personal baseline', 'linea_base.*' => 'baseline marker',
            'alergias' => 'allergies', 'alergias.*.sustancia' => 'allergy substance',
            'alergias.*.reaccion' => 'allergy reaction', 'alergias.*.gravedad' => 'allergy severity',
            'condiciones' => 'medical conditions', 'condiciones.*.nombre' => 'condition name',
            'condiciones.*.estado' => 'condition status', 'condiciones.*.desde' => 'condition start date',
            'condiciones.*.notas' => 'condition notes',
            'medicamentos' => 'medications', 'medicamentos.*.nombre' => 'medication name',
            'medicamentos.*.dosis_texto' => 'medication dose', 'medicamentos.*.frecuencia' => 'medication frequency',
            'medicamentos.*.motivo' => 'medication reason', 'medicamentos.*.desde' => 'medication start date',
            'medicamentos.*.activo' => 'active medication',
            'antecedentes' => 'family history', 'antecedentes.*.parentesco' => 'family history relationship',
            'antecedentes.*.condicion' => 'family history condition', 'antecedentes.*.edad_aparicion' => 'age of onset',
            'aptitud_vuelo' => 'flight fitness', 'restricciones' => 'restrictions',
            'ultimo_examen_en' => 'date of last exam', 'notas' => 'notes',
            'contactos' => 'emergency contacts', 'contactos.*.nombre' => 'contact name',
            'contactos.*.parentesco' => 'contact relationship', 'contactos.*.telefono' => 'contact phone number',
            'contactos.*.principal' => 'primary contact',
        ];
    }
}
