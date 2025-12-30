<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente {{ $expediente->numero_expediente }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            margin: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #1a1a1a;
        }
        .section {
            margin-bottom: 20px;
        }
        .field {
            margin-bottom: 10px;
        }
        .field-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .field-value {
            display: inline-block;
        }
        .description {
            margin-top: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border-left: 4px solid #333;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
            font-size: 10px;
            text-align: center;
            color: #666;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-proceso {
            background-color: #ffc107;
            color: #000;
        }
        .badge-finalizado {
            background-color: #28a745;
            color: #fff;
        }
        .badge-archivado {
            background-color: #6c757d;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>EXPEDIENTE LEGAL</h1>
        <p>{{ $expediente->numero_expediente }}</p>
    </div>

    <div class="section">
        <div class="field">
            <span class="field-label">Cliente:</span>
            <span class="field-value">{{ $expediente->nombre_cliente }}</span>
        </div>
        
        <div class="field">
            <span class="field-label">Tipo de Caso:</span>
            <span class="field-value">{{ $expediente->tipo_caso }}</span>
        </div>
        
        <div class="field">
            <span class="field-label">Estado Actual:</span>
            <span class="field-value">
                @php
                    $badgeClass = 'badge-proceso';
                    if ($expediente->estado_actual === 'FINALIZADO') {
                        $badgeClass = 'badge-finalizado';
                    } elseif ($expediente->estado_actual === 'ARCHIVADO') {
                        $badgeClass = 'badge-archivado';
                    }
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $expediente->estado_actual }}</span>
            </span>
        </div>
        
        <div class="field">
            <span class="field-label">Fecha de Inicio:</span>
            <span class="field-value">{{ $expediente->fecha_inicio }}</span>
        </div>
        
        @if($expediente->fecha_cierre)
        <div class="field">
            <span class="field-label">Fecha de Cierre:</span>
            <span class="field-value">{{ $expediente->fecha_cierre }}</span>
        </div>
        @endif
        
        @if($expediente->usuario)
        <div class="field">
            <span class="field-label">Responsable:</span>
            <span class="field-value">{{ $expediente->usuario->nombre }}</span>
        </div>
        @endif
    </div>

    @if($expediente->descripcion)
    <div class="description">
        <strong>Descripción:</strong>
        <p>{{ $expediente->descripcion }}</p>
    </div>
    @endif

    @if($expediente->notas)
    <div class="description">
        <strong>Notas:</strong>
        <p>{{ $expediente->notas }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Documento generado el {{ $fecha_generacion }}</p>
        <p>Este documento es confidencial y está destinado únicamente para uso interno.</p>
    </div>
</body>
</html>
