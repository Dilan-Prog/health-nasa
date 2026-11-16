<?php

namespace App\Services\Salud;

/** Stable machine-readable error codes for measurement validation. Logic must branch on these, never on message text. */
enum CodigoErrorMedicion: string
{
    case Missing = 'missing';
    case NotNumeric = 'not_numeric';
    case Impossible = 'impossible_value';
    case OutOfRange = 'out_of_range';
    case Duplicate = 'duplicate';
}
