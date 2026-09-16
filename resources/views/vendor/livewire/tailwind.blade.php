@if ($paginator->hasPages())
<style>
.pag-wrap { display:flex; align-items:center; gap:4px; }
.pag-wrap a, .pag-wrap span {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:30px; height:30px; padding:0 6px;
    border-radius:7px; border:1px solid #E5E7EB;
    font-size:12px; font-weight:500; color:#374151;
    background:#fff; text-decoration:none; cursor:pointer; transition:all .15s;
}
.pag-wrap a:hover { background:#EFF6FF; border-color:#1A5FB4; color:#1A5FB4; }
.pag-wrap span[aria-current="page"] { background:#1A5FB4; border-color:#1A5FB4; color:#fff; font-weight:700; }
.pag-wrap span.dots { border:none; background:transparent; color:#9CA3AF; cursor:default; }
</style>
<nav class="pag-wrap" aria-label="Pagination">
    {{-- Previous --}}
    @if ($paginator->onFirstPage())
        <span style="opacity:0.4;cursor:not-allowed">‹</span>
    @else
        <a wire:click="previousPage" wire:loading.attr="disabled">‹</a>
    @endif

    {{-- Pages --}}
    @foreach ($elements as $element)
        @if (is_string($element))
            <span class="dots">…</span>
        @endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span aria-current="page">{{ $page }}</span>
                @else
                    <a wire:click="gotoPage({{ $page }})">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Next --}}
    @if ($paginator->hasMorePages())
        <a wire:click="nextPage" wire:loading.attr="disabled">›</a>
    @else
        <span style="opacity:0.4;cursor:not-allowed">›</span>
    @endif
</nav>
@endif
