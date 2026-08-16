<?php

namespace App\Services;

class VectorMath
{
    /**
     * Similaridade de cosseno entre dois vetores — usada para pré-selecionar
     * candidatos (evidência x pergunta) antes de gastar chamada de LLM.
     * Retorna um valor entre -1 e 1 (na prática, entre 0 e 1 para embeddings
     * de texto normalizados).
     */
    public static function similaridadeCosseno(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $produtoEscalar = 0.0;
        $normaA = 0.0;
        $normaB = 0.0;

        foreach ($a as $i => $valor) {
            $produtoEscalar += $valor * $b[$i];
            $normaA += $valor ** 2;
            $normaB += $b[$i] ** 2;
        }

        if ($normaA == 0.0 || $normaB == 0.0) {
            return 0.0;
        }

        return $produtoEscalar / (sqrt($normaA) * sqrt($normaB));
    }
}
