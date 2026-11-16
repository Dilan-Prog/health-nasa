<?php

namespace App\Livewire;

use App\Contracts\Orientador;
use App\Models\CasoClinico;
use App\Services\Ia\Orientacion;
use App\Services\Salud\ConsultasTierra;
use App\Support\TripulanteActual;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Círculo flotante "Médico de vuelo" (IA local SIMULADA) incluido en layouts/app. Un mensaje por caso del tripulante actual.
 * El estado (mostrados, descartados, leídos, avisados) vive en la sesión porque las páginas recargan completas.
 * Orienta, no diagnostica: todo el contenido médico viene de la `Orientacion` del `Orientador`.
 */
class MedicoFlotante extends Component
{
    public const CLAVE = 'medico_flotante';

    public const AVISO = 'Esta es una versión simulada de una IA local. Puede cometer errores. Todo es simulado, a partir de la simulación de los tripulantes. Orienta, no diagnostica.';

    /** Horas que un caso cerrado sigue visible como mensaje (valor de interfaz, no clínico). */
    public const HORAS_CERRADO = 24;

    /** Máximo de mensajes (valor de interfaz, no clínico). */
    public const MAX = 10;

    /** Firma barata de los casos del tripulante: si no cambia, el poll no renderiza ni llama al Orientador. */
    public string $firma = '';

    /** Abrir el rectángulo al cargar la página (caso nuevo o escalado a crítico sin mostrar aún). */
    public bool $abrirAlCargar = false;

    public function mount(): void
    {
        $this->firma = $this->firmaActual();
        $r = $this->detectarNuevos();
        $this->abrirAlCargar = $r['abrir'];
    }

    /** Comprobación periódica (wire:poll) y por Reverb. Una sola consulta agregada; solo se renderiza si algo cambió. */
    #[On('medicion-registrada')]
    #[On('tripulante-cambiado')]
    public function comprobar(): void
    {
        $f = $this->firmaActual();
        if ($f === $this->firma) {
            $this->skipRender();

            return;
        }
        $this->firma = $f;
        $r = $this->detectarNuevos();
        if ($r['abrir']) {
            $this->dispatch('medico-flotante-abrir', critico: $r['critico'], casoId: $r['casoId']);
        }
    }

    /** "Entendido": descarta el caso en la sesión (reaparece solo si escala de severidad). */
    public function entender(int $casoId): void
    {
        $caso = $this->casoPropio($casoId);
        if (! $caso) {
            return;
        }
        $e = $this->estado();
        $e['descartados'][$caso->id] = $caso->severidad;
        $e['vistos'][$caso->id] = $caso->severidad;
        $e['leidos'][$caso->id] = $caso->severidad;
        $this->guardar($e);
    }

    public function marcarLeidos(): void
    {
        $a = TripulanteActual::obtener();
        if (! $a) {
            $this->skipRender();

            return;
        }
        $e = $this->estado();
        foreach ($this->consultaCasos($a->id)->get(['id', 'severidad']) as $c) {
            $e['leidos'][$c->id] = $c->severidad;
        }
        $this->guardar($e);
    }

    /** Reutiliza el flujo de la pantalla Médico de vuelo: consulta asíncrona a tierra con los indicadores del caso. */
    public function avisarATierra(int $casoId): void
    {
        $a = TripulanteActual::obtener();
        $caso = $this->casoPropio($casoId);
        if (! $a || ! $caso) {
            return;
        }
        $e = $this->estado();
        if ($e['avisados'][$caso->id] ?? false) {
            return;
        }
        $o = self::orientacionDe($caso);
        $indicadores = $o ? array_values(array_unique(array_column($o->historial7d, 'indicador'))) : [];
        try {
            ConsultasTierra::enviar($a, 'msg', $indicadores ?: [$caso->patron], $o ? $o->titulo.'. '.$o->hallazgo : null);
        } catch (InvalidArgumentException) {
            return;
        }
        $e['avisados'][$caso->id] = true;
        $this->guardar($e);
    }

    /** Orientación del caso, cacheada por caso + estado: el Orientador solo se llama para casos nuevos o cambiados. */
    public static function orientacionDe(CasoClinico $caso): ?Orientacion
    {
        $clave = 'ia.orientacion.'.$caso->id.'.'.($caso->updated_at?->timestamp ?? 0).'.'.$caso->severidad.'.'.$caso->estado;

        try {
            return Cache::remember($clave, 300, fn () => app(Orientador::class)->orientar($caso));
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Minigráfica (viewBox 120×32): x = i*20, y escalado a 4–28 (con 10 % de holgura) sobre la serie y la base.
     *
     * @return array{puntos:string, yBase:?float}
     */
    public static function minigrafica(array $serie, mixed $base = null): array
    {
        $v = array_values(array_map('floatval', $serie));
        if (! $v) {
            return ['puntos' => '', 'yBase' => null];
        }
        $b = is_numeric($base) ? (float) $base : null;
        $todos = $b === null ? $v : [...$v, $b];
        $lo = min($todos);
        $hi = max($todos);
        $y = fn (float $x): float => round($hi === $lo ? 16 : 28 - (0.1 + 0.8 * ($x - $lo) / ($hi - $lo)) * 24, 1);
        $pts = [];
        foreach ($v as $i => $x) {
            $pts[] = ($i * 20).','.$y($x);
        }

        return ['puntos' => implode(' ', $pts), 'yBase' => $b === null ? null : $y($b)];
    }

    /** Puntos "x,y" de una serie (compatibilidad). */
    public static function puntosSerie(array $serie): string
    {
        return self::minigrafica($serie)['puntos'];
    }

    /** Normaliza un elemento de expediente/tratamientos (string o array posicional/asociativo) a etiqueta/texto/nota. */
    public static function item(mixed $x): array
    {
        if (! is_array($x)) {
            return ['etiqueta' => null, 'texto' => (string) $x, 'nota' => null];
        }

        return [
            'etiqueta' => $x['etiqueta'] ?? $x[0] ?? null,
            'texto' => (string) ($x['texto'] ?? $x[1] ?? ''),
            'nota' => $x['nota'] ?? $x[2] ?? null,
        ];
    }

    public function render()
    {
        $a = TripulanteActual::obtener();
        $e = $this->estado();
        $mensajes = [];
        if ($a) {
            foreach ($this->consultaCasos($a->id)->limit(self::MAX)->get() as $caso) {
                if (($e['descartados'][$caso->id] ?? null) === $caso->severidad) {
                    continue;
                }
                $o = self::orientacionDe($caso);
                if (! $o) {
                    continue;
                }
                $mensajes[] = [
                    'caso' => $caso,
                    'o' => $o,
                    'abierto' => $caso->estado === 'abierto',
                    'avisado' => (bool) ($e['avisados'][$caso->id] ?? false) || ($caso->severidad === 'critico' && $o->escaladoEn),
                    'sinLeer' => $caso->estado === 'abierto' && ($e['leidos'][$caso->id] ?? null) !== $caso->severidad,
                ];
            }
        }
        $abiertos = array_filter($mensajes, fn ($m) => $m['abierto']);
        $sinLeer = array_values(array_filter($abiertos, fn ($m) => $m['sinLeer']));

        $critico = (bool) array_filter($abiertos, fn ($m) => $m['caso']->severidad === 'critico');

        return view('livewire.medico-flotante', [
            'astronauta' => $a,
            'mensajes' => $mensajes,
            'etiquetaCirculo' => 'Médico de vuelo'.($sinLeer ? ', '.count($sinLeer).' sin leer' : '').($critico ? ', caso crítico' : ''),
            'critico' => $critico,
            'nSinLeer' => count($sinLeer),
            'anuncio' => $sinLeer ? 'Médico de vuelo: '.implode('; ', array_map(fn ($m) => $m['o']->titulo.($m['caso']->severidad === 'critico' ? ' (crítico)' : ''), $sinLeer)).'.' : '',
            'aviso' => (string) config('ia.aviso', self::AVISO),
        ]);
    }

    /** Casos del tripulante: abiertos y cerrados recientes, el más reciente primero. */
    private function consultaCasos(int $astronautaId)
    {
        return CasoClinico::query()->where('astronauta_id', $astronautaId)
            ->where(fn ($q) => $q->where('estado', 'abierto')->orWhere('cerrado_en', '>=', now()->subHours(self::HORAS_CERRADO)))
            ->orderByDesc('id');
    }

    private function casoPropio(int $id): ?CasoClinico
    {
        $a = TripulanteActual::obtener();

        return $a ? CasoClinico::where('astronauta_id', $a->id)->find($id) : null;
    }

    /** Una sola consulta agregada: ¿hay casos nuevos o cambiados? */
    private function firmaActual(): string
    {
        $a = TripulanteActual::obtener();
        if (! $a) {
            return 'sin-tripulante';
        }
        $r = $this->consultaCasos($a->id)->reorder()
            ->selectRaw("count(*) c, max(id) i, max(updated_at) u, sum(severidad = 'critico') k, sum(estado = 'abierto') ab")->first();

        return $a->id.'|'.$r->c.'|'.$r->i.'|'.$r->u.'|'.$r->k.'|'.$r->ab;
    }

    /** Casos abiertos aún sin mostrar (o que subieron a crítico tras mostrarse); registra en la sesión lo mostrado. */
    private function detectarNuevos(): array
    {
        $a = TripulanteActual::obtener();
        $res = ['abrir' => false, 'critico' => false, 'casoId' => null];
        if (! $a) {
            return $res;
        }
        $e = $this->estado();
        $cambio = false;
        foreach (CasoClinico::abiertos()->where('astronauta_id', $a->id)->orderBy('id')->get(['id', 'severidad']) as $c) {
            if (($e['descartados'][$c->id] ?? null) === $c->severidad) {
                continue;
            }
            $visto = $e['vistos'][$c->id] ?? null;
            if ($visto === $c->severidad || $visto === 'critico') {
                continue;
            }
            $e['vistos'][$c->id] = $c->severidad;
            $cambio = true;
            $res['abrir'] = true;
            $res['casoId'] = $c->id;
            $res['critico'] = $res['critico'] || $c->severidad === 'critico';
        }
        if ($cambio) {
            $this->guardar($e);
        }

        return $res;
    }

    private function estado(): array
    {
        return array_merge(['vistos' => [], 'descartados' => [], 'leidos' => [], 'avisados' => []], (array) session(self::CLAVE, []));
    }

    private function guardar(array $e): void
    {
        session([self::CLAVE => $e]);
    }
}
