<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expediente;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Http\Request;

class EstadisticasController extends Controller
{
    /**
     * Get dashboard statistics.
     */
    public function index()
    {
        $stats = [
            'expedientes' => [
                'total' => Expediente::count(),
                'en_progreso' => Expediente::where('estado', 'EN_PROGRESO')->count(),
                'finalizados' => Expediente::where('estado', 'CERRADO')->count(),
                'urgentes' => Expediente::whereIn('estado', ['URGENTE', 'CRITICO'])->count(),
                'pendientes' => Expediente::where('estado', 'PENDIENTE')->count(),
            ],
            'usuarios' => [
                'total' => User::count(),
            ],
            'mensajes' => [
                'total' => Contact::count(),
                'recientes' => Contact::where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'expedientes_recientes' => Expediente::orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Get expedientes statistics by estado.
     */
    public function expedientesPorEstado()
    {
        $stats = Expediente::selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->get()
            ->pluck('total', 'estado');

        return response()->json($stats);
    }

    /**
     * Get expedientes statistics by materia.
     */
    public function expedientesPorMateria()
    {
        $stats = Expediente::selectRaw('materia, COUNT(*) as total')
            ->groupBy('materia')
            ->get()
            ->pluck('total', 'materia');

        return response()->json($stats);
    }
}
