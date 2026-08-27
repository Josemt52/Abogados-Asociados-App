<?php

return [
    'disk' => env('CARGA_MASIVA_DISK', 'local'),
    'max_archivos' => (int) env('CARGA_MASIVA_MAX_ARCHIVOS', 50),
    'max_kilobytes_por_archivo' => (int) env('CARGA_MASIVA_MAX_KB', 10240),
    'max_docx_descomprimido' => (int) env('CARGA_MASIVA_MAX_DOCX_UNCOMPRESSED', 52428800),
    'max_entradas_docx' => (int) env('CARGA_MASIVA_MAX_DOCX_ENTRIES', 2000),
    'max_caracteres_primera_pagina' => (int) env('CARGA_MASIVA_MAX_FIRST_PAGE_CHARS', 12000),
    'ocr' => [
        'binary' => env('TESSERACT_BINARY', 'tesseract'),
        'language' => env('TESSERACT_LANGUAGE', 'spa'),
        'timeout' => (int) env('TESSERACT_TIMEOUT', 120),
        'max_pixeles' => (int) env('TESSERACT_MAX_PIXELS', 25000000),
    ],
];
