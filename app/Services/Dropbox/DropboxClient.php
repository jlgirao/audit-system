<?php

namespace App\Services\Dropbox;

use App\Models\DropboxConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DropboxClient
{
    private const AUTHORIZE_URL = 'https://www.dropbox.com/oauth2/authorize';

    private const TOKEN_URL = 'https://api.dropboxapi.com/oauth2/token';

    private const API_URL = 'https://api.dropboxapi.com/2';

    private const CONTENT_URL = 'https://content.dropboxapi.com/2';

    // Sem construtor com dependência de DropboxConfig aqui de propósito:
    // quando o Laravel resolve DropboxClient automaticamente (injeção em
    // controllers/jobs), ele tentaria instanciar um DropboxConfig "vazio"
    // sozinho (é uma classe concreta, sem argumentos obrigatórios) em vez
    // de deixar null, e o ??-fallback nunca seria acionado. Por isso a
    // config é sempre buscada direto do banco via DropboxConfig::atual().

    /**
     * Monta a URL para o admin autorizar o app (redireciona para o Dropbox).
     * token_access_type=offline é o que garante o refresh_token, necessário
     * para renovar o acesso sem o admin precisar reconectar toda hora.
     */
    public function urlDeAutorizacao(): string
    {
        $query = http_build_query([
            'client_id' => config('services.dropbox.client_id'),
            'response_type' => 'code',
            'token_access_type' => 'offline',
            'redirect_uri' => config('services.dropbox.redirect'),
        ]);

        return self::AUTHORIZE_URL.'?'.$query;
    }

    /**
     * Troca o código de autorização (retornado no callback) pelos tokens.
     */
    public function trocarCodigoPorToken(string $code): array
    {
        $resposta = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => config('services.dropbox.client_id'),
            'client_secret' => config('services.dropbox.client_secret'),
            'redirect_uri' => config('services.dropbox.redirect'),
        ]);

        if ($resposta->failed()) {
            throw new RuntimeException('Falha ao trocar código pelo token do Dropbox: '.$resposta->body());
        }

        return $resposta->json();
    }

    /**
     * Garante que existe um access_token válido, renovando via refresh_token
     * se necessário. Chamado internamente antes de qualquer requisição à API.
     */
    private function garantirTokenValido(): string
    {
        $config = DropboxConfig::atual();

        if (! $config->conectado()) {
            throw new RuntimeException('Dropbox não está conectado. Peça para o admin conectar em /admin/dropbox.');
        }

        if (! $config->tokenExpirado()) {
            return $config->access_token;
        }

        $resposta = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $config->refresh_token,
            'client_id' => config('services.dropbox.client_id'),
            'client_secret' => config('services.dropbox.client_secret'),
        ]);

        if ($resposta->failed()) {
            throw new RuntimeException('Falha ao renovar token do Dropbox: '.$resposta->body());
        }

        $dados = $resposta->json();

        $config->access_token = $dados['access_token'];
        $config->token_expira_em = now()->addSeconds($dados['expires_in'] ?? 14400);
        $config->save();

        return $config->access_token;
    }

    /**
     * Lista apenas as SUBPASTAS diretas de um caminho (não recursivo, e
     * ignora arquivos) — usado pelo seletor de pastas na criação/edição
     * de processo, para o usuário navegar visualmente em vez de digitar
     * o caminho manualmente.
     *
     * @return array{caminho_atual: string, pastas: array<int, array{nome: string, caminho: string}>}
     */
    public function listarSubpastas(string $caminho = ''): array
    {
        $token = $this->garantirTokenValido();

        // A API do Dropbox usa string vazia para representar a raiz,
        // não "/".
        $caminhoNormalizado = ($caminho === '/' || $caminho === '') ? '' : $caminho;

        $resposta = Http::withToken($token)->post(self::API_URL.'/files/list_folder', [
            'path' => $caminhoNormalizado,
            'recursive' => false,
        ]);

        if ($resposta->failed()) {
            throw new RuntimeException('Falha ao listar pastas do Dropbox: '.$resposta->body());
        }

        $entradas = $resposta->json('entries', []);

        $pastas = collect($entradas)
            ->filter(fn ($entrada) => ($entrada['.tag'] ?? null) === 'folder')
            ->map(fn ($entrada) => [
                'nome' => $entrada['name'],
                'caminho' => $entrada['path_display'],
            ])
            ->sortBy('nome')
            ->values()
            ->all();

        return [
            'caminho_atual' => $caminhoNormalizado,
            'pastas' => $pastas,
        ];
    }

    /**
     * Lista o conteúdo de uma pasta (recursivo). Usar sem cursor na
     * primeira sincronização de um processo.
     */
    public function listarPasta(string $caminho): array
    {
        $token = $this->garantirTokenValido();

        // Mesma normalização do listarSubpastas: a API do Dropbox usa
        // string vazia para representar a raiz, e rejeita "/" com erro.
        $caminhoNormalizado = ($caminho === '/' || $caminho === '') ? '' : rtrim($caminho, '/');

        $resposta = Http::withToken($token)->post(self::API_URL.'/files/list_folder', [
            'path' => $caminhoNormalizado,
            'recursive' => true,
            'include_deleted' => true,
        ]);

        if ($resposta->failed()) {
            throw new RuntimeException('Falha ao listar pasta do Dropbox ('.$caminho.'): '.$resposta->body());
        }

        return $resposta->json();
    }

    /**
     * Continua a listagem a partir de um cursor salvo — só retorna o que
     * mudou desde a última sincronização (muito mais barato que relistar
     * tudo sempre).
     */
    public function continuarListagem(string $cursor): array
    {
        $token = $this->garantirTokenValido();

        $resposta = Http::withToken($token)->post(self::API_URL.'/files/list_folder/continue', [
            'cursor' => $cursor,
        ]);

        if ($resposta->failed()) {
            // Cursor inválido/expirado (ex: pasta foi recriada). Quem chamar
            // deve tratar isso reiniciando a sincronização sem cursor.
            throw new DropboxCursorInvalidoException($resposta->body());
        }

        return $resposta->json();
    }

    /**
     * Baixa o conteúdo bruto de um arquivo.
     */
    public function baixarArquivo(string $caminho): string
    {
        $token = $this->garantirTokenValido();

        $resposta = Http::withToken($token)
            ->withHeaders(['Dropbox-API-Arg' => json_encode(['path' => $caminho])])
            ->post(self::CONTENT_URL.'/files/download');

        if ($resposta->failed()) {
            throw new RuntimeException('Falha ao baixar arquivo do Dropbox ('.$caminho.'): '.$resposta->body());
        }

        return $resposta->body();
    }
}