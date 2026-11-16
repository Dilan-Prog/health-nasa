<?php

namespace App\Livewire;

use App\Models\AlertaEvento;
use App\Models\AlertaGestion;
use App\Models\AlertaSaliente;
use App\Services\Salud\EnvioATierra;
use App\Services\Salud\ResumenTripulante;
use App\Support\TripulanteActual;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla Alertas (diseño "Panel tripulación"): bandeja + detalle (A), tablero por estado (B) y escalera de
 * escalamiento + línea de tiempo (C). Las alertas se derivan de los indicadores en precaución/crítico del tripulante;
 * reconocer / escalar / resolver se guardan en alertas_gestion y alertas_eventos. Orienta, no diagnostica.
 */
#[Layout('layouts.app')]
class Alertas extends Component
{
    public const NIVELES = [
        ['Autocuidado', 'self_improvement', 'El tripulante aplica las acciones sugeridas'],
        ['Comandante', 'military_tech', 'Valoración a bordo por el comandante'],
        ['Flight surgeon', 'stethoscope', 'Valoración médica desde tierra'],
    ];

    public const ESTADOS = [
        'nueva' => ['Nueva', 'fiber_new'], 'vista' => ['Reconocida', 'visibility'],
        'escalada' => ['Escalada', 'trending_up'], 'resuelta' => ['Resuelta', 'check_circle'],
    ];

    public const SEV = ['crit' => ['GRAVE', 'error'], 'warn' => ['ATENCIÓN', 'warning'], 'ok' => ['RESUELTA', 'check_circle']];

    /** a = bandeja y detalle · b = tablero · c = escalera */
    #[Url(as: 'v', except: 'a')]
    public string $vista = 'a';

    /** activas | graves | resueltas */
    #[Url(as: 'f', except: 'activas')]
    public string $filtro = 'activas';

    public string $sel = '';

    #[On('tripulante-cambiado')]
    public function refrescar(): void
    {
        $this->sel = '';
    }

    public function seleccionar(string $clave): void
    {
        $this->sel = $clave;
    }

    public function reconocer(string $clave): void
    {
        $this->actuar($clave, 'vista', 'reconocida', 'visibility', 'warn');
    }

    public function escalar(string $clave): void
    {
        $this->actuar($clave, 'escalada', 'escalada', 'trending_up');
    }

    public function resolver(string $clave): void
    {
        $this->actuar($clave, 'resuelta', 'resuelta', 'check_circle', 'ok');
    }

    public function reabrir(string $clave): void
    {
        $this->actuar($clave, 'vista', 'reabierta', 'replay');
    }

    private function actuar(string $clave, string $estado, string $verbo, string $icono, ?string $sev = null): void
    {
        $a = TripulanteActual::obtener();
        $alerta = $a ? collect($this->alertas($a))->firstWhere('clave', $clave) : null;
        if (! $a || ! $alerta) {
            return;
        }
        $nivel = $alerta['lvl'];
        if ($estado === 'escalada') {
            if ($alerta['est'] === 'resuelta' || $nivel >= 2) {
                return;
            }
            $nivel++;
        } elseif ($verbo === 'reconocida' && $alerta['est'] !== 'nueva') {
            return;
        }

        AlertaGestion::updateOrCreate(
            ['astronauta_id' => $a->id, 'indicador' => $clave],
            ['medido_en' => $alerta['medido_en'], 'severidad' => $alerta['s'], 'valor' => $alerta['v'],
                'razon' => $alerta['why'], 'estado' => $estado, 'nivel' => $nivel],
        );

        $msg = $verbo === 'escalada'
            ? $alerta['ind'].' escalada a '.self::NIVELES[$nivel][0]
            : $alerta['ind'].': '.$verbo;
        AlertaEvento::create(['astronauta_id' => $a->id, 'indicador' => $clave, 'mensaje' => $msg, 'icono' => $icono,
            'severidad' => $sev ?? $alerta['s']]);

        // Llegar al flight surgeon encola el aviso a tierra (sale cuando haya enlace).
        if ($estado === 'escalada' && $nivel === 2) {
            AlertaSaliente::create(['astronauta_id' => $a->id, 'estado' => 'pendiente', 'carga' => [
                'astronauta' => $a->nombre, 'indicador' => $clave, 'valor' => $alerta['v'],
                'severidad' => $alerta['s'], 'motivo' => $alerta['why'], 'origen' => 'escalamiento_manual']]);
        }
    }

    public function render()
    {
        if (! in_array($this->vista, ['a', 'b', 'c'], true)) {
            $this->vista = 'a';
        }
        if (! in_array($this->filtro, ['activas', 'graves', 'resueltas'], true)) {
            $this->filtro = 'activas';
        }
        $a = TripulanteActual::obtener();
        $todas = $a ? $this->alertas($a) : [];
        $act = array_values(array_filter($todas, fn ($x) => $x['est'] !== 'resuelta'));
        $shown = match ($this->filtro) {
            'graves' => array_values(array_filter($act, fn ($x) => $x['s'] === 'crit')),
            'resueltas' => array_values(array_filter($todas, fn ($x) => $x['est'] === 'resuelta')),
            default => $act,
        };
        $det = collect($todas)->firstWhere('clave', $this->sel) ?? $shown[0] ?? $act[0] ?? ($todas[0] ?? null);

        $cuenta = fn (callable $f) => count(array_filter($todas, $f));
        $conteos = [
            ['n' => $cuenta(fn ($x) => $x['s'] === 'crit' && $x['est'] !== 'resuelta'), 'l' => 'graves', 'k' => 'crit', 'ic' => 'error'],
            ['n' => $cuenta(fn ($x) => $x['s'] === 'warn' && $x['est'] !== 'resuelta'), 'l' => 'en atención', 'k' => 'warn', 'ic' => 'warning'],
            ['n' => $cuenta(fn ($x) => $x['est'] === 'nueva'), 'l' => 'sin reconocer', 'k' => null, 'ic' => 'fiber_new'],
            ['n' => $cuenta(fn ($x) => $x['est'] === 'resuelta'), 'l' => 'resueltas', 'k' => 'ok', 'ic' => 'check_circle'],
        ];
        $columnas = [];
        foreach (['nueva' => 'Nuevas', 'vista' => 'Reconocidas', 'escalada' => 'Escaladas', 'resuelta' => 'Resueltas'] as $k => $l) {
            $columnas[] = ['l' => $l, 'ic' => self::ESTADOS[$k][1], 'items' => array_values(array_filter($todas, fn ($x) => $x['est'] === $k))];
        }
        $escalera = [];
        foreach (self::NIVELES as $i => [$l, $ic, $quien]) {
            $items = array_values(array_filter($act, fn ($x) => $x['lvl'] === $i));
            $peor = collect($items)->contains(fn ($x) => $x['s'] === 'crit') ? 'crit' : ($items ? 'warn' : null);
            $escalera[] = ['l' => $l, 'ic' => $ic, 'who' => $quien, 'items' => $items, 'peor' => $peor];
        }

        return view('livewire.alertas', [
            'astronauta' => $a, 'conteos' => $conteos, 'lista' => $shown, 'det' => $det, 'columnas' => $columnas,
            'escalera' => $escalera, 'bitacora' => $a ? $this->bitacora($a, $act) : [], 'niveles' => self::NIVELES,
            'enlace' => app(EnvioATierra::class)->enlaceActivo(),
        ]);
    }

    /** Alertas ordenadas: graves primero y, dentro, las nuevas; luego las resueltas. */
    private function alertas($a): array
    {
        $resumen = ResumenTripulante::make()->de($a);
        $gestion = AlertaGestion::where('astronauta_id', $a->id)->get()->keyBy('indicador');
        $out = [];
        foreach ($resumen['incidencias'] as $i) {
            $g = $gestion->get($i['clave']);
            $vigente = $g && $i['medido_en'] && $g->medido_en && $g->medido_en->equalTo($i['medido_en']);
            if ($vigente && $g->estado === 'resuelta') {
                $out[] = $this->resuelta($i['clave'], $g);
                continue;
            }
            $out[] = $this->tarjeta([
                'clave' => $i['clave'], 'ind' => $i['etiqueta'], 'ic' => $i['icono'],
                'v' => $i['valor'].' '.$i['unidad'], 's' => $i['estado'], 'medido_en' => $i['medido_en'],
                'est' => $vigente ? $g->estado : 'nueva', 'lvl' => $vigente ? $g->nivel : 0,
                'why' => $i['razon'] ?? '', 'act' => $i['acciones'][0] ?? 'Consulta al flight surgeon.',
            ]);
        }
        $activas = array_column($out, 'clave');
        foreach ($gestion as $clave => $g) {
            if ($g->estado === 'resuelta' && ! in_array($clave, $activas, true) && config('panel.indicadores.'.$clave)) {
                $out[] = $this->resuelta($clave, $g);
            }
        }
        $ord = ['crit' => 0, 'warn' => 1];
        usort($out, fn ($x, $y) => [$x['est'] === 'resuelta', $ord[$x['s']], $x['est'] !== 'nueva']
            <=> [$y['est'] === 'resuelta', $ord[$y['s']], $y['est'] !== 'nueva']);

        return $out;
    }

    private function resuelta(string $clave, AlertaGestion $g): array
    {
        $cfg = config('panel.indicadores.'.$clave);

        return $this->tarjeta([
            'clave' => $clave, 'ind' => $cfg['etiqueta'], 'ic' => $cfg['icono'], 'v' => $g->valor.' '.$cfg['unidad'],
            's' => $g->severidad, 'medido_en' => $g->medido_en, 'est' => 'resuelta', 'lvl' => $g->nivel,
            'why' => (string) $g->razon, 'act' => 'Resuelta. Sigue tu rutina y vuelve a medir según tu plan.',
        ]);
    }

    private function tarjeta(array $x): array
    {
        $res = $x['est'] === 'resuelta';
        $sev = $res ? 'ok' : $x['s'];

        return $x + [
            'tono' => $sev, 'sevL' => self::SEV[$sev][0], 'sevIc' => self::SEV[$sev][1],
            'estL' => self::ESTADOS[$x['est']][0], 'estIc' => self::ESTADOS[$x['est']][1],
            'lvlL' => 'Nivel '.($x['lvl'] + 1).' · '.self::NIVELES[$x['lvl']][0], 'lvlIc' => self::NIVELES[$x['lvl']][1],
            'ago' => $this->hace($x['medido_en']),
            't' => $x['medido_en'] ? $x['medido_en']->copy()->utc()->format('d/m H:i') : '—',
            'abierta' => ! $res, 'puedeAck' => $x['est'] === 'nueva', 'puedeEsc' => ! $res && $x['lvl'] < 2,
            'escL' => $x['lvl'] < 2 ? 'Escalar a '.self::NIVELES[$x['lvl'] + 1][0] : '',
        ];
    }

    private function hace(?Carbon $t): string
    {
        if (! $t) {
            return '';
        }
        $min = max(0, (int) $t->diffInMinutes(now('UTC'), false));

        return match (true) {
            $min < 1 => 'ahora', $min < 60 => "hace {$min} min", $min < 1440 => 'hace '.intdiv($min, 60).' h',
            default => 'hace '.intdiv($min, 1440).' d',
        };
    }

    /** Acciones guardadas + detecciones derivadas de las alertas activas, de más reciente a más antigua. */
    private function bitacora($a, array $act): array
    {
        $filas = AlertaEvento::where('astronauta_id', $a->id)->latest()->limit(12)->get()->map(fn ($e) => [
            'at' => $e->created_at->copy()->utc(), 'msg' => $e->mensaje, 'ic' => $e->icono, 'k' => $e->severidad])->all();
        foreach ($act as $x) {
            if ($x['medido_en']) {
                $filas[] = ['at' => $x['medido_en']->copy()->utc(), 'msg' => $x['ind'].' detectada: '.$x['v'],
                    'ic' => 'notifications_active', 'k' => $x['s']];
            }
        }
        usort($filas, fn ($p, $q) => $q['at'] <=> $p['at']);

        return array_map(fn ($f) => ['t' => $f['at']->format('d/m H:i')] + $f, array_slice($filas, 0, 12));
    }
}
