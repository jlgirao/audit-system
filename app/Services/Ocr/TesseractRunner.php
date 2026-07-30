<?php

namespace App\Services\Ocr;

use RuntimeException;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Throwable;

class TesseractRunner
{
    public function extrairTexto(string $caminhoImagem): string
    {
        try {
            return (new TesseractOCR($caminhoImagem))
                ->executable(config('ocr.tesseract_binario'))
                ->lang(config('ocr.idioma'))
                ->run();
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Tesseract OCR falhou. Confirme se está instalado, se o caminho em '.
                'TESSERACT_BINARY (.env) está correto, e se o idioma "'.config('ocr.idioma').
                '" foi instalado junto com o Tesseract. Detalhe: '.$e->getMessage()
            );
        }
    }
}
