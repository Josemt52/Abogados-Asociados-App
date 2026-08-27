<?php

namespace App\Services;

use App\Models\Expediente;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class ExpedienteNumberLock
{
    /**
     * Serializa altas y correcciones que representan el mismo número canónico.
     * Debe invocarse dentro de la transacción que consulta o crea el expediente.
     */
    public function acquire(string $number): string
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('El bloqueo de número requiere una transacción activa.');
        }

        $normalized = Expediente::normalizarNumero($number);
        if ($normalized === '') {
            throw new InvalidArgumentException('El número de expediente es obligatorio.');
        }

        DB::table('expediente_numero_locks')->insertOrIgnore([
            'numero_normalizado' => $normalized,
        ]);
        DB::table('expediente_numero_locks')
            ->where('numero_normalizado', $normalized)
            ->lockForUpdate()
            ->first();

        return $normalized;
    }
}
