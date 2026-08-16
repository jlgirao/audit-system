<?php

namespace App\Services\Ocr;

use RuntimeException;
use Symfony\Component\Process\Process;

class PdfToImageConverter
{
    /**
     * Converte cada página do PDF em um arquivo PNG dentro de $diretorioSaida.
     * Usa o Ghostscript diretamente via linha de comando — evita depender
     * da extensão Imagick, que é notoriamente difícil de instalar em
     * ambientes Windows/XAMPP.
     *
     * @return array<int, string> caminhos das imagens geradas, em ordem de página
     */
    public function converter(string $caminhoPdf, string $diretorioSaida): array
    {
        $binario = config('ocr.ghostscript_binario');
        $dpi = config('ocr.dpi');
        $padraoSaida = $diretorioSaida.DIRECTORY_SEPARATOR.'pagina-%03d.png';

        $processo = new Process([
            $binario,
            '-dNOPAUSE',
            '-dBATCH',
            '-dSAFER',
            '-sDEVICE=png16m',
            "-r{$dpi}",
            "-sOutputFile={$padraoSaida}",
            $caminhoPdf,
        ]);
        $processo->setTimeout(180);
        $processo->run();

        if (! $processo->isSuccessful()) {
            throw new RuntimeException(
                'Ghostscript falhou ao converter o PDF em imagens. Confirme se está instalado '.
                'e se o caminho em GHOSTSCRIPT_BINARY (.env) está correto. Detalhe: '.
                $processo->getErrorOutput()
            );
        }

        $imagens = glob($diretorioSaida.DIRECTORY_SEPARATOR.'pagina-*.png') ?: [];
        sort($imagens);

        return $imagens;
    }
}
