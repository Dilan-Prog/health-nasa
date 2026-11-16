<?php

namespace App\Services\Salud;

use App\Models\Requisito;

/**
 * Resuelve el requisito (tabla `requisitos`) del que cuelga un indicador, según config/trazabilidad.php.
 * Devuelve null si el indicador no tiene requisito o la fila no existe. Caché en memoria por petición.
 */
class ResolvedorRequisito
{
    /** @var array<string, Requisito> */
    private static array $cache = [];

    /** @param array{entorno?:string,condicion?:string,banda?:string} $contexto */
    public static function para(string $indicador, array $contexto = []): ?Requisito
    {
        $entrada = self::entrada($indicador, $contexto);

        return $entrada ? self::buscar($entrada) : null;
    }

    /** Requisito normativo que acompaña a un vínculo no normativo (p. ej. V1 4027 en pérdida ósea). */
    public static function referenciaDe(string $indicador): ?Requisito
    {
        $entrada = config('trazabilidad.referencias.'.$indicador);

        return $entrada ? self::buscar($entrada) : null;
    }

    /** @return array{tipo:string,revision:?string,validado:bool,codigo:?string,texto:string,etiqueta:string,es_proyeccion:bool}|null */
    public static function fuente(?Requisito $r): ?array
    {
        if (! $r) {
            return null;
        }
        $proyeccion = $r->tipo === 'hrp';

        return [
            'tipo' => $r->tipo,
            'revision' => $r->revision,
            'validado' => (bool) $r->validado,
            'codigo' => $r->codigo,
            'texto' => $r->texto,
            'etiqueta' => $proyeccion ? 'Proyección (HRP)' : (Requisito::TIPOS[$r->tipo] ?? (string) $r->tipo),
            'es_proyeccion' => $proyeccion,
        ];
    }

    /** Limpia la caché (tests, o tras cambiar la tabla dentro de la misma petición). */
    public static function olvidar(): void
    {
        self::$cache = [];
    }

    private static function entrada(string $indicador, array $contexto): ?array
    {
        foreach (['banda', 'condicion', 'entorno'] as $campo) {
            $valor = $contexto[$campo] ?? null;
            if ($valor !== null && ($e = config("trazabilidad.condiciones.$indicador.$campo.$valor"))) {
                return $e;
            }
        }

        return config('trazabilidad.indicadores.'.$indicador);
    }

    /** Una sola consulta carga la tabla completa (son pocas filas); una tabla vacía no se recuerda. */
    private static function buscar(array $e): ?Requisito
    {
        if (self::$cache === []) {
            foreach (Requisito::all() as $r) {
                self::$cache[$r->tipo.'|'.$r->dominio.'|'.$r->texto] = $r;
            }
        }

        return self::$cache[$e['tipo'].'|'.$e['dominio'].'|'.$e['texto']] ?? null;
    }
}
