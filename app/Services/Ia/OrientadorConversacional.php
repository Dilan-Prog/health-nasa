<?php

namespace App\Services\Ia;

use App\Models\Astronauta;
use App\Models\ConsultaSintomas;
use App\Models\RegistroSalud;
use App\Services\Auditoria;
use App\Services\Salud\ConsultasTierra;
use App\Services\Salud\ResumenTripulante;
use InvalidArgumentException;
use ReflectionMethod;

/**
 * Orquesta el orientador conversacional SIMULADO: AnalizadorSintomas -> ContextoTripulante -> RazonadorSimulado,
 * y guarda la consulta (texto cifrado). Orienta, no diagnostica. La auditoría no recibe valores médicos.
 *
 * Los colaboradores se pueden inyectar como closures (pruebas): 'analizar'(texto), 'desdeEntidades'(sintomas),
 * 'contexto'(Astronauta), 'razonar'(analisis, contexto, opciones), 'accionesPanel'(Astronauta). Por defecto usan
 * los servicios reales (métodos estáticos o de instancia, da igual).
 */
class OrientadorConversacional
{
    /** @param array<string, callable> $colaboradores */
    public function __construct(private array $colaboradores = [])
    {
    }

    /** Analiza el texto libre y guarda la consulta. @return array{consulta_id:int, analisis:array, respuesta:array} */
    public function responder(Astronauta $a, string $texto, array $opciones = []): array
    {
        $texto = $this->limpiar($texto);
        $analisis = $this->colab('analizar', fn () => $this->llamar(AnalizadorSintomas::class, 'analizar', $texto), $texto);
        $analisis['texto'] = $texto;

        return $this->resolver($a, $texto, $analisis, $opciones);
    }

    /** Vuelve a razonar con síntomas editados a mano ("Entendí" editable). @param array<int, array> $sintomas */
    public function responderConEntidades(Astronauta $a, string $texto, array $sintomas, array $opciones = []): array
    {
        $texto = $this->limpiar($texto);
        $analisis = $this->colab('desdeEntidades', fn () => $this->llamar(AnalizadorSintomas::class, 'desdeEntidades', $sintomas), $sintomas);
        $analisis['texto'] = $texto;

        return $this->resolver($a, $texto, $analisis, $opciones);
    }

    /** "Sigue igual" / "Empeoró": nueva evaluación con la anterior como referencia (no repite el mensaje). */
    public function reevaluar(int $consultaId, string $evolucion): array
    {
        if (! in_array($evolucion, ['igual', 'empeoro'], true)) {
            throw new InvalidArgumentException('Evolución no válida: '.$evolucion);
        }
        $previa = ConsultaSintomas::with('astronauta')->findOrFail($consultaId);
        $analisis = ($previa->entidades ?? []) + ['sintomas' => [], 'negados' => [], 'banderas_rojas' => [], 'entendido' => true, 'confianza' => 0];
        $analisis['texto'] = (string) $previa->texto;

        return $this->resolver($previa->astronauta, (string) $previa->texto, $analisis, ['evolucion' => $evolucion, 'urgencia_previa' => $previa->urgencia]);
    }

    public function marcarUtil(int $consultaId, bool $util): void
    {
        ConsultaSintomas::whereKey($consultaId)->update(['util' => $util]);
    }

    /** Crea un RegistroSalud con los síntomas entendidos (etiquetas; el texto libre no se copia a una columna sin cifrar). */
    public function guardarEnRegistro(int $consultaId): RegistroSalud
    {
        $c = ConsultaSintomas::findOrFail($consultaId);
        $et = $this->etiquetas($c->entidades['sintomas'] ?? []);
        $mapa = ['mareo' => 'Mareo', 'dolor_cabeza' => 'Dolor de cabeza', 'nauseas' => 'Náuseas', 'falta_aire' => 'Falta de aire', 'fatiga' => 'Fatiga'];
        $estandar = array_values(array_unique(array_filter(array_map(fn ($s) => $mapa[$s['clave'] ?? ''] ?? null, $c->entidades['sintomas'] ?? []))));

        $registro = RegistroSalud::create([
            'astronauta_id' => $c->astronauta_id,
            'origen' => 'manual',
            'sintomas' => $estandar ?: null,
            'nota' => 'Orientador (simulado): '.($et ? implode('; ', $et) : 'sin síntomas interpretados').'.',
            'aviso_tierra' => false,
            'registrado_en' => now(),
        ]);
        Auditoria::registrar('crear', 'registro_salud', $registro->id, $c->astronauta_id);

        return $registro;
    }

    /** Consulta asíncrona a tierra con el resumen de los síntomas (igual que MedicoFlotante::avisarATierra con un caso). */
    public function avisarATierra(int $consultaId): bool
    {
        $c = ConsultaSintomas::with('astronauta')->findOrFail($consultaId);
        if (! $c->astronauta) {
            return false;
        }
        $panel = (array) config('panel.indicadores', []);
        $ind = [];
        foreach ($c->respuesta['seguimiento'] ?? [] as $s) {
            if (! empty($s['indicador']) && isset($panel[$s['indicador']])) {
                $ind[] = $s['indicador'];
            }
        }
        $ind = array_values(array_unique($ind)) ?: array_values(array_filter((array) config('orientador.indicadores_emergencia', []), fn ($k) => isset($panel[$k])));
        $ind = $ind ?: array_slice(array_keys($panel), 0, 1);
        if (! $ind) {
            return false;
        }
        $et = $this->etiquetas($c->entidades['sintomas'] ?? []);
        $nota = 'Orientador simulado ('.$c->urgencia.'): '.($et ? implode('; ', $et) : 'síntomas sin interpretar').'. Aviso del tripulante.';
        try {
            ConsultasTierra::enviar($c->astronauta, 'msg', $ind, $nota);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /** Etiquetas legibles de las entidades (para resumen y registro). */
    public function etiquetas(array $sintomas): array
    {
        return array_values(array_filter(array_map(fn ($s) => (string) ($s['etiqueta'] ?? $s['clave'] ?? ''), $sintomas)));
    }

    private function resolver(Astronauta $a, string $texto, array $analisis, array $opciones): array
    {
        $contexto = $this->colab('contexto', fn () => $this->llamar(ContextoTripulante::class, 'de', $a), $a);
        $opciones += ['evolucion' => null, 'urgencia_previa' => null];
        $opciones['acciones_panel'] ??= $this->accionesPanel($a);

        $respuesta = $this->colab('razonar', fn () => $this->llamar(RazonadorSimulado::class, 'razonar', $analisis, $contexto, $opciones), $analisis, $contexto, $opciones);
        $respuesta['evolucion'] = $opciones['evolucion'];

        $entidades = $analisis;
        unset($entidades['texto']);
        $consulta = ConsultaSintomas::create([
            'astronauta_id' => $a->id,
            'texto' => $texto,
            'entidades' => $entidades,
            'urgencia' => (string) ($respuesta['urgencia'] ?? 'observacion'),
            'respuesta' => $respuesta,
        ]);
        // Sin valores médicos: solo quién, qué recurso y cuál.
        Auditoria::registrar('enviar', 'orientador', $consulta->id, $a->id);

        return ['consulta_id' => $consulta->id, 'analisis' => $analisis, 'respuesta' => $respuesta];
    }

    /** Ids de las acciones que el Panel ya muestra en "Qué hacer ahora". */
    private function accionesPanel(Astronauta $a): array
    {
        return (array) $this->colab('accionesPanel', function () use ($a) {
            $resumen = ResumenTripulante::make()->de($a);

            return $this->llamar(AccionesPanel::class, 'idsDe', $resumen['incidencias'] ?? []);
        }, $a);
    }

    private function limpiar(string $texto): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');

        return rtrim(mb_substr($texto, 0, (int) config('orientador.max_caracteres', 500)));
    }

    private function colab(string $clave, callable $porDefecto, mixed ...$args): mixed
    {
        return isset($this->colaboradores[$clave]) ? ($this->colaboradores[$clave])(...$args) : $porDefecto();
    }

    private function llamar(string $clase, string $metodo, mixed ...$args): mixed
    {
        $r = new ReflectionMethod($clase, $metodo);

        return $r->isStatic() ? $clase::$metodo(...$args) : app($clase)->$metodo(...$args);
    }
}
