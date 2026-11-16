<?php

namespace App\Livewire;

use App\Models\Astronauta;
use App\Models\ConsultaTierra;
use App\Services\Salud\ConsultasTierra;
use App\Services\Salud\EnvioATierra;
use App\Services\Salud\OrientacionAlertas;
use App\Support\TripulanteActual;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pantalla "Médico de vuelo": tarjetas de orientación (regla de emergencia / asistente IA local / regla fija),
 * qué compartir, nota, adjuntos y seguimiento de las consultas enviadas a tierra. Orienta, no diagnostica.
 */
#[Layout('layouts.app')]
class MedicoVuelo extends Component
{
    public const SEV = [
        'crit' => ['Crítico', 'error'],
        'warn' => ['Revisar', 'warning'],
    ];

    public const SELLO = [
        'verif' => ['Verificado contra botiquín', 'verified_user', 'ok'],
        'escal' => ['Escalado a humano', 'supervisor_account', 'warn'],
        'rule' => ['Protocolo fijo verificado · sin IA', 'verified_user', 'ok'],
        'none' => ['Sin orientación de IA', 'rule', 'neu'],
    ];

    /** Indicadores marcados para compartir (id => bool). */
    public array $marcados = [];

    /** Posiciones de los adjuntos elegidos. */
    public array $adjuntosSel = [];

    public string $nota = '';

    /** Fuente abierta por tarjeta (id => posición). */
    public array $fuente = [];

    /** Tarjetas añadidas al mensaje (id => true). */
    public array $anadidas = [];

    /** Votos ya registrados que muestran el mensaje de gracias (id => 'up'|'down'). */
    public array $gracias = [];

    public string $anuncio = '';

    public ?string $error = null;

    public function mount(): void
    {
        $this->adjuntosSel = ['0', '1', '2'];
    }

    #[On('tripulante-cambiado')]
    public function reiniciar(): void
    {
        $this->marcados = [];
        $this->fuente = [];
        $this->anadidas = [];
        $this->gracias = [];
        $this->nota = '';
        $this->error = null;
        $this->anuncio = '';
    }

    #[On('medicion-registrada')]
    public function refrescar(): void
    {
        // Solo vuelve a renderizar: las tarjetas se calculan en cada render.
    }

    public function abrirFuente(string $id, int $i): void
    {
        if (($this->fuente[$id] ?? null) === $i) {
            unset($this->fuente[$id]);
        } else {
            $this->fuente[$id] = $i;
        }
    }

    public function votar(string $id, string $voto): void
    {
        $a = TripulanteActual::obtener();
        if (! $a || ! in_array($voto, ['up', 'down'], true)) {
            return;
        }
        $res = ConsultasTierra::votar($a, $id, $voto);
        if ($res) {
            $this->gracias[$id] = $res;
            $this->anuncio = 'Gracias por tu opinión.';
        } else {
            unset($this->gracias[$id]);
            $this->anuncio = 'Opinión retirada.';
        }
    }

    public function anadir(string $id): void
    {
        $this->anadidas[$id] = true;
        $base = preg_replace('/^(em-|r-)/', '', $id);
        $this->marcados[$id] = true;
        $this->marcados[$base] = true;
        $this->anuncio = 'Añadido al mensaje para el médico de vuelo.';
    }

    public function borrador(): void
    {
        $a = TripulanteActual::obtener();
        if (! $a) {
            return;
        }
        $ids = $this->idsElegidos($this->compartibles($a));
        $partes = [];
        foreach ($this->compartibles($a) as $s) {
            if (in_array($s['id'], $ids, true)) {
                $partes[] = mb_strtolower($s['label']).' '.$s['val'];
            }
        }
        $this->nota = 'Hola, soy '.$a->nombre.'.'
            .($partes ? ' Comparto: '.implode('; ', $partes).'.' : ' Hoy todo está en rango.')
            .' ¿Podemos revisar el plan en la próxima ventana?';
    }

    public function enviar(string $tipo): void
    {
        $this->error = null;
        $a = TripulanteActual::obtener();
        if (! $a) {
            $this->error = 'No hay tripulante seleccionado.';

            return;
        }
        $ids = $this->idsElegidos($this->compartibles($a));
        $adj = [];
        foreach (ConsultasTierra::adjuntosDisponibles($a) as $i => $x) {
            if (in_array((string) $i, array_map('strval', $this->adjuntosSel), true)) {
                $adj[] = $x['l'];
            }
        }
        try {
            ConsultasTierra::enviar($a, $tipo, $ids, trim($this->nota) !== '' ? trim($this->nota) : null, $adj);
        } catch (InvalidArgumentException $e) {
            $this->error = $ids === [] ? 'Elige al menos una alerta para compartir.' : $e->getMessage();
            $this->anuncio = $this->error;

            return;
        }
        $this->anuncio = $tipo === 'video' ? 'Videoconsulta solicitada.' : 'Mensaje en cola.';
        $this->nota = '';
        $this->anadidas = [];
    }

    public function reintentar(int $id): void
    {
        $a = TripulanteActual::obtener();
        $c = $a ? ConsultaTierra::where('astronauta_id', $a->id)->find($id) : null;
        if (! $c) {
            return;
        }
        $this->anuncio = ConsultasTierra::reintentar($c)
            ? 'Envío reintentado. El enlace está activo.'
            : 'Sigue sin enlace. Se enviará automáticamente al recuperarlo.';
    }

    /** @return array<int, array{id:string,label:string,val:string,sev:string,locked:bool}> */
    private function compartibles(Astronauta $a): array
    {
        return OrientacionAlertas::compartibles($a);
    }

    /** Ids marcados (bloqueados incluidos) a partir de lo elegido y de los valores por defecto. */
    private function idsElegidos(array $comp): array
    {
        $ids = [];
        foreach ($comp as $s) {
            $on = $s['locked'] || ($this->marcados[$s['id']] ?? $s['sev'] === 'crit');
            if ($on) {
                $ids[] = $s['id'];
            }
        }

        return $ids;
    }

    public function render()
    {
        $a = TripulanteActual::obtener();
        $tarjetas = $a ? OrientacionAlertas::para($a) : [];
        $comp = $a ? $this->compartibles($a) : [];
        foreach ($comp as $s) {
            $this->marcados[$s['id']] ??= $s['locked'] || $s['sev'] === 'crit';
        }

        $votos = [];
        foreach ($tarjetas as $t) {
            if ($a && $t['kind'] === 'ai') {
                $votos[$t['id']] = ConsultasTierra::voto($a, $t['id']);
            }
        }

        $hayEmerg = collect($tarjetas)->contains(fn ($t) => $t['kind'] === 'emerg');
        $retardo = $a ? $a->retardoMin() : 0;
        $enlace = app(EnvioATierra::class)->enlaceActivo();

        return view('livewire.medico-vuelo', [
            'astronauta' => $a,
            'tarjetas' => $tarjetas,
            'emergencia' => collect($tarjetas)->firstWhere('kind', 'emerg'),
            'hayEmerg' => $hayEmerg,
            'nPausa' => $hayEmerg ? collect($tarjetas)->where('kind', 'ai')->count() : 0,
            'votos' => $votos,
            'compartibles' => $comp,
            'elegidos' => $this->idsElegidos($comp),
            'adjuntos' => $a ? ConsultasTierra::adjuntosDisponibles($a) : [],
            'envios' => $a ? ConsultasTierra::lista($a, 10) : [],
            'retardo' => $retardo,
            'enlace' => $enlace,
            'puedeVideo' => $retardo < 5 && $enlace,
        ]);
    }
}
