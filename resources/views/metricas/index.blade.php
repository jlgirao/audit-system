@extends('layouts.app')

@section('titulo', 'Métricas de acerto da IA')

@section('conteudo')
    <h2>Métricas de acerto da IA</h2>
    <p style="color:#666; font-size:13px;">
        Baseado nas decisões que analistas/auditores já tomaram sobre as sugestões da IA
        (confirmar ou rejeitar). Sugestões ainda não revisadas não contam para a taxa de acerto.
    </p>

    <div style="display:flex; gap:16px; margin-bottom:24px; flex-wrap:wrap;">
        <div style="background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px; flex:1; min-width:160px;">
            <p style="font-size:12px; color:#666; margin:0 0 4px;">Total de sugestões geradas</p>
            <p style="font-size:24px; font-weight:bold; margin:0;">{{ $geral->total ?? 0 }}</p>
        </div>
        <div style="background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px; flex:1; min-width:160px;">
            <p style="font-size:12px; color:#666; margin:0 0 4px;">Confirmadas</p>
            <p style="font-size:24px; font-weight:bold; margin:0; color:#166534;">{{ $geral->confirmadas ?? 0 }}</p>
        </div>
        <div style="background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px; flex:1; min-width:160px;">
            <p style="font-size:12px; color:#666; margin:0 0 4px;">Rejeitadas</p>
            <p style="font-size:24px; font-weight:bold; margin:0; color:#991b1b;">{{ $geral->rejeitadas ?? 0 }}</p>
        </div>
        <div style="background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px; flex:1; min-width:160px;">
            <p style="font-size:12px; color:#666; margin:0 0 4px;">Ainda pendentes de revisão</p>
            <p style="font-size:24px; font-weight:bold; margin:0; color:#92400e;">{{ $geral->pendentes ?? 0 }}</p>
        </div>
        <div style="background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px; flex:1; min-width:160px;">
            <p style="font-size:12px; color:#666; margin:0 0 4px;">Taxa de acerto</p>
            <p style="font-size:24px; font-weight:bold; margin:0;">
                {{ $taxaAcertoGeral !== null ? $taxaAcertoGeral.'%' : '—' }}
            </p>
        </div>
    </div>

    @if (($geral->confianca_media_confirmadas ?? null) !== null || ($geral->confianca_media_rejeitadas ?? null) !== null)
        <p style="font-size:13px; color:#666; margin-bottom:24px;">
            Confiança média das sugestões <strong>confirmadas</strong>:
            {{ $geral->confianca_media_confirmadas ? round($geral->confianca_media_confirmadas, 1).'%' : '—' }}
            &nbsp;|&nbsp;
            Confiança média das <strong>rejeitadas</strong>:
            {{ $geral->confianca_media_rejeitadas ? round($geral->confianca_media_rejeitadas, 1).'%' : '—' }}
            <br>
            <span style="font-size:12px;">
                Se a confiança média das rejeitadas estiver próxima da das confirmadas, o campo de
                confiança do LLM não está discriminando bem — pode valer a pena revisar o prompt.
            </span>
        </p>
    @endif

    <h3>Por pergunta</h3>
    <table>
        <thead>
        <tr>
            <th>Código</th>
            <th>Pergunta</th>
            <th>Total</th>
            <th>Confirmadas</th>
            <th>Rejeitadas</th>
            <th>Pendentes</th>
            <th>Taxa de acerto</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($porPergunta as $linha)
            <tr>
                <td>{{ $linha->codigo }}</td>
                <td>{{ \Illuminate\Support\Str::limit($linha->texto_pergunta, 60) }}</td>
                <td>{{ $linha->total }}</td>
                <td style="color:#166534;">{{ $linha->confirmadas }}</td>
                <td style="color:#991b1b;">{{ $linha->rejeitadas }}</td>
                <td style="color:#92400e;">{{ $linha->pendentes }}</td>
                <td>
                    @if ($linha->taxa_acerto !== null)
                        <div style="display:flex; align-items:center; gap:6px;">
                            <div style="background:#e5e7eb; border-radius:4px; width:80px; height:10px; overflow:hidden;">
                                <div style="background:{{ $linha->taxa_acerto >= 70 ? '#166534' : ($linha->taxa_acerto >= 40 ? '#92400e' : '#991b1b') }}; width:{{ $linha->taxa_acerto }}%; height:100%;"></div>
                            </div>
                            <span style="font-size:12px;">{{ $linha->taxa_acerto }}%</span>
                        </div>
                    @else
                        <span style="color:#999; font-size:12px;">sem revisão ainda</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7">Nenhuma sugestão da IA registrada ainda.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3 style="margin-top:32px;">Por processo</h3>
    <table>
        <thead>
        <tr>
            <th>Processo</th>
            <th>Total</th>
            <th>Confirmadas</th>
            <th>Rejeitadas</th>
            <th>Pendentes</th>
            <th>Taxa de acerto</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($porProcesso as $linha)
            <tr>
                <td>{{ $linha->nome }}</td>
                <td>{{ $linha->total }}</td>
                <td style="color:#166534;">{{ $linha->confirmadas }}</td>
                <td style="color:#991b1b;">{{ $linha->rejeitadas }}</td>
                <td style="color:#92400e;">{{ $linha->pendentes }}</td>
                <td>{{ $linha->taxa_acerto !== null ? $linha->taxa_acerto.'%' : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Nenhuma sugestão da IA registrada ainda.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
