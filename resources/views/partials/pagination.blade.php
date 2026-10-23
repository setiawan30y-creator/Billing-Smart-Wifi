@if ($paginator->hasPages())
<nav class="pagination">
  <span class="info">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}</span>
  @if ($paginator->onFirstPage())<span class="disabled">‹</span>@else<a href="{{ $paginator->previousPageUrl() }}">‹</a>@endif
  @foreach ($elements as $element)
    @if (is_string($element))<span class="disabled">{{ $element }}</span>@endif
    @if (is_array($element))
      @foreach ($element as $page => $url)
        @if ($page == $paginator->currentPage())<span class="current">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif
      @endforeach
    @endif
  @endforeach
  @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">›</a>@else<span class="disabled">›</span>@endif
</nav>
@endif
