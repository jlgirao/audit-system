@if ($paginator->hasPages())
    <nav style="display:flex; align-items:center; gap:10px; font-size:13px; margin-top:12px; flex-wrap:wrap;">
        @if ($paginator->onFirstPage())
            <span style="color:#bbb;">« Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}">« Anterior</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span style="color:#999;">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <strong>{{ $page }}</strong>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}">Próxima »</a>
        @else
            <span style="color:#bbb;">Próxima »</span>
        @endif
    </nav>

    <p style="font-size:12px; color:#666; margin-top:4px;">
        Mostrando {{ $paginator->firstItem() ?? 0 }} a {{ $paginator->lastItem() ?? 0 }}
        de {{ $paginator->total() }} resultado(s)
    </p>
@endif
