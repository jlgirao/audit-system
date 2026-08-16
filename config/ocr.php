<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR (Fase 2)
    |--------------------------------------------------------------------------
    |
    | Caminhos dos binários usados para OCR de imagens e PDFs escaneados.
    | Em Linux/Mac, geralmente basta o nome do comando ("tesseract", "gs")
    | se estiverem no PATH. No Windows, normalmente é necessário o caminho
    | completo do .exe — configure via .env.
    |
    */

    'tesseract_binario' => env('TESSERACT_BINARY', 'tesseract'),

    'idioma' => env('OCR_IDIOMA', 'por'),

    // No Windows, o binário de linha de comando do Ghostscript costuma se
    // chamar "gswin64c.exe" (ou "gswin32c.exe" em sistemas 32-bit), não "gs".
    'ghostscript_binario' => env('GHOSTSCRIPT_BINARY', 'gs'),

    // Resolução usada ao converter páginas de PDF em imagem antes do OCR.
    // Mais alto = OCR mais preciso, porém mais lento e mais pesado.
    'dpi' => env('OCR_DPI', 300),

];
