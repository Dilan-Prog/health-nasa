<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Cifrado en reposo (AES-256 de Laravel vía Crypt, con APP_KEY). Siempre escribe cifrado.
 * Al leer es tolerante: si el valor guardado no es un valor cifrado (dato antiguo en texto plano), lo devuelve tal cual,
 * de modo que se puede migrar poco a poco con `expediente:cifrar`. Si APP_KEY cambia, lo ya cifrado queda ilegible
 * (se devuelve el texto cifrado tal cual, sin lanzar excepción).
 */
class EncryptedFlexible implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }

        return self::descifrar((string) $value) ?? (string) $value;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }

    /** Texto en claro si $valor es un valor cifrado válido con la clave actual; null si no lo es. */
    public static function descifrar(string $valor): ?string
    {
        try {
            return Crypt::decryptString($valor);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function estaCifrado(?string $valor): bool
    {
        return $valor !== null && self::descifrar($valor) !== null;
    }
}
