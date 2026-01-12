<?php

namespace App\Services;

use App\Models\Expediente;
use Barryvdh\DomPDF\Facade\Pdf;

class PDFDocumentService
{
    public function generateExpedienteDocument(Expediente $expediente): \Barryvdh\DomPDF\PDF
    {
        $data = [
            'expediente' => $expediente,
            'fecha_generacion' => now()->format('d/m/Y H:i:s'),
        ];
        
        return Pdf::loadView('pdf.expediente', $data)
            ->setPaper('a4', 'portrait');
    }
}
