<?php

namespace App\Services\Salud;

use Illuminate\Validation\ValidationException;

/**
 * ValidationException with the same shape as before (errors()['valores'] => messages),
 * plus the typed error codes, recoverable through codigos().
 */
class MedicionInvalidaException extends ValidationException
{
    /** @var CodigoErrorMedicion[] */
    private array $codigos = [];

    /** @param array<int, array{codigo: CodigoErrorMedicion, mensaje: string}> $errores */
    public static function desde(array $errores): self
    {
        $e = static::withMessages(['valores' => array_column($errores, 'mensaje')]);
        $e->codigos = array_column($errores, 'codigo');

        return $e;
    }

    /** @return CodigoErrorMedicion[] */
    public function codigos(): array
    {
        return $this->codigos;
    }

    public function tiene(CodigoErrorMedicion $codigo): bool
    {
        return in_array($codigo, $this->codigos, true);
    }
}
