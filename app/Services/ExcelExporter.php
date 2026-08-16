<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExporter
{
    /**
     * Gera um .xlsx simples (uma aba, cabeçalho na linha 1) e devolve como
     * download direto — sem gravar nada em disco.
     *
     * Usa apenas `setCellValue()` com coordenadas em string (ex: "B3"),
     * em vez de `setCellValueByColumnAndRow()` — esse segundo método foi
     * removido em versões mais novas do PhpSpreadsheet (2.x).
     *
     * @param  array<int, string>  $cabecalhos
     * @param  iterable<int, array<int, mixed>>  $linhas  cada item é um array de valores, na mesma ordem dos cabeçalhos
     */
    public static function gerar(array $cabecalhos, iterable $linhas, string $nomeArquivo): StreamedResponse
    {
        $planilha = new Spreadsheet();
        $aba = $planilha->getActiveSheet();

        foreach ($cabecalhos as $indice => $titulo) {
            $coluna = Coordinate::stringFromColumnIndex($indice + 1);
            $aba->setCellValue($coluna.'1', $titulo);
        }

        $ultimaColuna = Coordinate::stringFromColumnIndex(max(count($cabecalhos), 1));
        $aba->getStyle('A1:'.$ultimaColuna.'1')->getFont()->setBold(true);

        $linhaAtual = 2;

        foreach ($linhas as $linha) {
            foreach (array_values($linha) as $indiceColuna => $valor) {
                $coluna = Coordinate::stringFromColumnIndex($indiceColuna + 1);
                $aba->setCellValue($coluna.$linhaAtual, $valor);
            }
            $linhaAtual++;
        }

        foreach (range(1, count($cabecalhos)) as $indiceColuna) {
            $coluna = Coordinate::stringFromColumnIndex($indiceColuna);
            $aba->getColumnDimension($coluna)->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($planilha, 'Xlsx');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nomeArquivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
