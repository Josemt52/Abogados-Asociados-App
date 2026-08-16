<?php

namespace App\Services;

use InvalidArgumentException;

class SpanishNumberService
{
    /**
     * Convert a non-negative integer to Spanish words.
     */
    public function toWords(int $number): string
    {
        if ($number < 0 || $number > 999999999) {
            throw new InvalidArgumentException('El número debe estar entre 0 y 999999999.');
        }

        if ($number === 0) {
            return 'CERO';
        }

        if ($number < 1000) {
            return $this->underThousand($number);
        }

        if ($number < 1000000) {
            $thousands = intdiv($number, 1000);
            $remainder = $number % 1000;
            $prefix = $thousands === 1 ? 'MIL' : $this->apocopate($this->underThousand($thousands)).' MIL';

            return trim($prefix.' '.($remainder > 0 ? $this->underThousand($remainder) : ''));
        }

        $millions = intdiv($number, 1000000);
        $remainder = $number % 1000000;
        $prefix = $millions === 1
            ? 'UN MILLÓN'
            : $this->apocopate($this->toWords($millions)).' MILLONES';

        return trim($prefix.' '.($remainder > 0 ? $this->toWords($remainder) : ''));
    }

    private function underThousand(int $number): string
    {
        $units = [
            0 => '', 1 => 'UNO', 2 => 'DOS', 3 => 'TRES', 4 => 'CUATRO', 5 => 'CINCO',
            6 => 'SEIS', 7 => 'SIETE', 8 => 'OCHO', 9 => 'NUEVE', 10 => 'DIEZ',
            11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE', 15 => 'QUINCE',
            16 => 'DIECISÉIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE',
            20 => 'VEINTE', 21 => 'VEINTIUNO', 22 => 'VEINTIDÓS', 23 => 'VEINTITRÉS',
            24 => 'VEINTICUATRO', 25 => 'VEINTICINCO', 26 => 'VEINTISÉIS',
            27 => 'VEINTISIETE', 28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE',
        ];

        if ($number < 30) {
            return $units[$number];
        }

        if ($number < 100) {
            $tens = [3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA'];
            $ten = intdiv($number, 10);
            $unit = $number % 10;

            return $tens[$ten].($unit > 0 ? ' Y '.$units[$unit] : '');
        }

        if ($number === 100) {
            return 'CIEN';
        }

        $hundreds = [
            1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS',
            5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS',
            8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS',
        ];
        $hundred = intdiv($number, 100);
        $remainder = $number % 100;

        return trim($hundreds[$hundred].' '.($remainder > 0 ? $this->underThousand($remainder) : ''));
    }

    private function apocopate(string $words): string
    {
        $words = preg_replace('/VEINTIUNO$/u', 'VEINTIÚN', $words) ?? $words;

        return preg_replace('/UNO$/u', 'UN', $words) ?? $words;
    }
}
