<?php

namespace App\Http\Controllers;

use App\Services\Dropbox\DropboxClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Throwable;

class DropboxBrowseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        // Autenticação basta aqui: quem já tem acesso às telas de criar/
        // editar processo (analista, auditor, admin) precisa poder navegar
        // pelas pastas para escolher o caminho corretamente.
        return ['auth'];
    }

    public function pastas(Request $request, DropboxClient $client)
    {
        $caminho = (string) $request->input('caminho', '');

        try {
            $dados = $client->listarSubpastas($caminho);
        } catch (Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        return response()->json($dados);
    }
}
