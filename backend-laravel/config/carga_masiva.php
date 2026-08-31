<?php

return [
    'disk' => env('CARGA_MASIVA_DISK', 'local'),
    'max_archivos' => (int) env('CARGA_MASIVA_MAX_ARCHIVOS', 50),
    'max_kilobytes_por_archivo' => (int) env('CARGA_MASIVA_MAX_KB', 10240),
    'max_docx_descomprimido' => (int) env('CARGA_MASIVA_MAX_DOCX_UNCOMPRESSED', 52428800),
    'max_entradas_docx' => (int) env('CARGA_MASIVA_MAX_DOCX_ENTRIES', 2000),
    'max_caracteres_primera_pagina' => (int) env('CARGA_MASIVA_MAX_FIRST_PAGE_CHARS', 12000),
    'pdf' => [
        'pdfinfo_binary' => env('PDFINFO_BINARY', 'pdfinfo'),
        'pdftotext_binary' => env('PDFTOTEXT_BINARY', 'pdftotext'),
        'pdftoppm_binary' => env('PDFTOPPM_BINARY', 'pdftoppm'),
        'max_pages' => (int) env('CARGA_MASIVA_PDF_MAX_PAGES', 100),
        'ocr_long_side_pixels' => (int) env('CARGA_MASIVA_PDF_OCR_LONG_SIDE', 3508),
        'docx_long_side_pixels' => (int) env('CARGA_MASIVA_PDF_DOCX_LONG_SIDE', 2100),
        'max_docx_bytes' => (int) env('CARGA_MASIVA_PDF_MAX_DOCX_BYTES', 31457280),
        'extraction_timeout' => (int) env('CARGA_MASIVA_PDF_EXTRACTION_TIMEOUT', 120),
        'conversion_timeout' => (int) env('CARGA_MASIVA_PDF_CONVERSION_TIMEOUT', 360),
    ],
    'ocr' => [
        'binary' => env('TESSERACT_BINARY', 'tesseract'),
        'language' => env('TESSERACT_LANGUAGE', 'spa'),
        'timeout' => (int) env('TESSERACT_TIMEOUT', 120),
        'max_pixeles' => (int) env('TESSERACT_MAX_PIXELS', 25000000),
    ],
];
