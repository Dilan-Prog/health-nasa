<?php

namespace App\Services\Salud;

enum Estado: string
{
    case Normal = 'normal';
    case Precaucion = 'precaucion';
    case Critico = 'critico';

    public function severidad(): int
    {
        return match ($this) {
            self::Normal => 0,
            self::Precaucion => 1,
            self::Critico => 2,
        };
    }

    /** Devuelve el más grave de los dos estados. */
    public function peor(self $otro): self
    {
        return $otro->severidad() > $this->severidad() ? $otro : $this;
    }
}
