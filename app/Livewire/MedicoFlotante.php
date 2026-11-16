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

    /** Puntos "x,y" de una minigráfica (viewBox 100×28). */
    public static function puntosSerie(array $serie): string
    {
        $v = array_values(array_map('floatval', $serie));
        if (! $v) {
            return '';
        }
        $lo = min($v);
        $hi = max($v);
        $n = max(count($v) - 1, 1);
        $pts = [];
        foreach ($v as $i => $x) {
            $y = $hi === $lo ? 14 : 25 - ($x - $lo) / ($hi - $lo) * 22;
            $pts[] = round($i * 100 / $n, 1).','.round($y, 1);
        }

        return implode(' ', $pts);
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
                    'avisado' => (bool) ($e['avisados'][$caso->id] ?? false),
                    'sinLeer' => $caso->estado === 'abierto' && ($e['leidos'][$caso->id] ?? null) !== $caso->severidad,
                ];
            }
        }
        $abiertos = array_filter($mensajes, fn ($m) => $m['abierto']);
        $sinLeer = array_values(array_filter($abiertos, fn ($m) => $m['sinLeer']));

        return view('livewire.medico-flotante', [
            'astronauta' => $a,
            'mensajes' => $mensajes,
            'critico' => (bool) array_filter($abiertos, fn ($m) => $m['caso']->severidad === 'critico'),
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
