<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Expediente {{ $expediente->numero }}</title>
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            margin: 40px;
        }
        h1 {
            font-size: 14pt;
            font-weight: bold;
        }
        .field {
            font-size: 12pt;
            margin-bottom: 15px;
        }
        .estado-section {
            margin-top: 30px;
        }
        .estado-title {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .estado-content {
            font-size: 10pt;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <h1>EXPEDIENTE: {{ $expediente->numero }}</h1>
    
    <div class="field">MATERIA: {{ $expediente->materia ?? '' }}</div>
    <div class="field">JUEZ: {{ $expediente->juzgado ?? '' }}</div>
    <div class="field">ESPECIALISTA: {{ $expediente->especialista ?? '' }}</div>
    <div class="field">TERCERO: {{ $expediente->tercero ?? '' }}</div>
    <div class="field">DEMANDADO: {{ $expediente->demandado ?? '' }}</div>
    <div class="field">DEMANDANTE: {{ $expediente->demandante ?? '' }}</div>
    
    <div class="estado-section">
        <div class="estado-title">Estado Actual del Expediente:</div>
        <div class="estado-content">
            {{ strip_tags($expediente->estado ?? '') }}
        </div>
    </div>
</body>
</html>
