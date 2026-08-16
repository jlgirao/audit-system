{{-- Reutilizável em qualquer listagem paginada. Preserva todos os filtros/
     ordenação já ativos na URL, só troca (ou adiciona) o per_page. --}}
<div style="display:flex; align-items:center; gap:6px; font-size:13px; margin-bottom:8px;">
    <label for="per_page_selector" style="font-weight:normal; margin:0;">Linhas por página:</label>
    <select id="per_page_selector" onchange="atualizarPerPage(this.value)" style="width:auto; margin-top:0; padding:4px 8px;">
        @foreach ([10, 20, 50, 100] as $opcao)
            <option value="{{ $opcao }}" @selected((int) request('per_page', 20) === $opcao)>{{ $opcao }}</option>
        @endforeach
    </select>
</div>

<script>
    function atualizarPerPage(valor) {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', valor);
        url.searchParams.set('page', 1); // volta para a primeira página ao trocar a quantidade
        window.location.href = url.toString();
    }
</script>
