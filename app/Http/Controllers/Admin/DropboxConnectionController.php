<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DropboxConfig;
use App\Services\Dropbox\DropboxClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class DropboxConnectionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'permission:configurar-dropbox',
        ];
    }

    public function index()
    {
        $config = DropboxConfig::atual();

        return view('admin.dropbox.index', ['config' => $config]);
    }

    public function conectar(DropboxClient $client)
    {
        return redirect()->away($client->urlDeAutorizacao());
    }

    public function callback(Request $request, DropboxClient $client)
    {
        if ($request->has('error')) {
            return redirect()->route('admin.dropbox.index')
                ->withErrors(['dropbox' => 'Autorização cancelada ou negada no Dropbox.']);
        }

        $dados = $request->validate(['code' => ['required', 'string']]);

        $tokenResposta = $client->trocarCodigoPorToken($dados['code']);

        $config = DropboxConfig::atual();
        $config->fill([
            'access_token' => $tokenResposta['access_token'],
            'refresh_token' => $tokenResposta['refresh_token'],
            'token_expira_em' => now()->addSeconds($tokenResposta['expires_in'] ?? 14400),
            'conectado_por' => $request->user()->id,
            'conectado_em' => now(),
        ]);
        $config->save();

        return redirect()->route('admin.dropbox.index')->with('status', 'Dropbox conectado com sucesso.');
    }

    public function desconectar()
    {
        $config = DropboxConfig::atual();
        $config->fill([
            'access_token' => null,
            'refresh_token' => null,
            'token_expira_em' => null,
        ])->save();

        return redirect()->route('admin.dropbox.index')->with('status', 'Dropbox desconectado.');
    }
}
