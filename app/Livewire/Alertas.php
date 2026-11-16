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
        ['Self-care', 'self_improvement', 'The crew member applies the suggested actions'],
        ['Commander', 'military_tech', 'On-board assessment by the commander'],
        ['Flight Surgeon', 'stethoscope', 'Medical assessment from the ground'],
    ];

    public const ESTADOS = [
        'nueva' => ['New', 'fiber_new'], 'vista' => ['Acknowledged', 'visibility'],
        'escalada' => ['Escalated', 'trending_up'], 'resuelta' => ['Resolved', 'check_circle'],
    ];

    public const SEV = ['crit' => ['CRITICAL', 'error'], 'warn' => ['CAUTION', 'warning'], 'ok' => ['RESOLVED', 'check_circle']];

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

        $verbos = ['reconocida' => 'acknowledged', 'resuelta' => 'resolved', 'reabierta' => 'reopened'];
        $msg = $verbo === 'escalada'
            ? $alerta['ind'].' escalated to '.self::NIVELES[$nivel][0]
            : $alerta['ind'].': '.($verbos[$verbo] ?? $verbo);
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
            ['n' => $cuenta(fn ($x) => $x['s'] === 'crit' && $x['est'] !== 'resuelta'), 'l' => 'critical', 'k' => 'crit', 'ic' => 'error'],
            ['n' => $cuenta(fn ($x) => $x['s'] === 'warn' && $x['est'] !== 'resuelta'), 'l' => 'caution', 'k' => 'warn', 'ic' => 'warning'],
            ['n' => $cuenta(fn ($x) => $x['est'] === 'nueva'), 'l' => 'unacknowledged', 'k' => null, 'ic' => 'fiber_new'],
            ['n' => $cuenta(fn ($x) => $x['est'] === 'resuelta'), 'l' => 'resolved', 'k' => 'ok', 'ic' => 'check_circle'],
        ];
        $columnas = [];
        foreach (['nueva' => 'New', 'vista' => 'Acknowledged', 'escalada' => 'Escalated', 'resuelta' => 'Resolved'] as $k => $l) {
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
                'why' => $i['razon'] ?? '', 'act' => $i['acciones'][0] ?? 'Consult the Flight Surgeon.',
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
            'why' => (string) $g->razon, 'act' => 'Resolved. Follow your routine and measure again according to your plan.',
        ]);
    }

    private function tarjeta(array $x): array
    {
        $res = $x['est'] === 'resuelta';
        $sev = $res ? 'ok' : $x['s'];

        return $x + [
            'tono' => $sev, 'sevL' => self::SEV[$sev][0], 'sevIc' => self::SEV[$sev][1],
            'estL' => self::ESTADOS[$x['est']][0], 'estIc' => self::ESTADOS[$x['est']][1],
            'lvlL' => 'Level '.($x['lvl'] + 1).' · '.self::NIVELES[$x['lvl']][0], 'lvlIc' => self::NIVELES[$x['lvl']][1],
            'ago' => $this->hace($x['medido_en']),
            't' => $x['medido_en'] ? $x['medido_en']->copy()->utc()->format('d M H:i') : '—',
            'abierta' => ! $res, 'puedeAck' => $x['est'] === 'nueva', 'puedeEsc' => ! $res && $x['lvl'] < 2,
            'escL' => $x['lvl'] < 2 ? 'Escalate to '.self::NIVELES[$x['lvl'] + 1][0] : '',
        ];
    }

    private function hace(?Carbon $t): string
    {
        if (! $t) {
            return '';
        }
        $min = max(0, (int) $t->diffInMinutes(now('UTC'), false));

        return match (true) {
            $min < 1 => 'now', $min < 60 => "{$min} min ago", $min < 1440 => intdiv($min, 60).' h ago',
            default => intdiv($min, 1440).' d ago',
        };
    }

    /** Acciones guardadas + detecciones derivadas de las alertas activas, de más reciente a más antigua. */
    private function bitacora($a, array $act): array
    {
        $filas = AlertaEvento::where('astronauta_id', $a->id)->latest()->limit(12)->get()->map(fn ($e) => [
            'at' => $e->created_at->copy()->utc(), 'msg' => $e->mensaje, 'ic' => $e->icono, 'k' => $e->severidad])->all();
        foreach ($act as $x) {
            if ($x['medido_en']) {
                $filas[] = ['at' => $x['medido_en']->copy()->utc(), 'msg' => $x['ind'].' detected: '.$x['v'],
                    'ic' => 'notifications_active', 'k' => $x['s']];
            }
        }
        usort($filas, fn ($p, $q) => $q['at'] <=> $p['at']);

        return array_map(fn ($f) => ['t' => $f['at']->format('d M H:i')] + $f, array_slice($filas, 0, 12));
    }
}
