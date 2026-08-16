{{-- Espera: $coluna (nome da coluna no banco/ordenável) e $label (texto exibido).
     Preserva todos os outros parâmetros da URL (busca, status, per_page, etc). --}}
@php
    $ordenandoPorEsta = request('sort') === $coluna;
    $direcaoAtual = request('direction', 'asc');
    $novaDirecao = ($ordenandoPorEsta && $direcaoAtual === 'asc') ? 'desc' : 'asc';
    $seta = $ordenandoPorEsta ? ($direcaoAtual === 'asc' ? ' ▲' : ' ▼') : '';
@endphp
<th>
    <a href="{{ request()->fullUrlWithQuery(['sort' => $coluna, 'direction' => $novaDirecao, 'page' => 1]) }}"
        style="color:inherit; text-decoration:none; white-space:nowrap;">
        {{ $label }}{{ $seta }}
    </a>
</th>
