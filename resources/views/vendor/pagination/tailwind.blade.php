@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="pg-nav">
        <p class="pg-summary">
            {!! __('Showing') !!}
            @if ($paginator->firstItem())
                <strong>{{ $paginator->firstItem() }}</strong>
                {!! __('to') !!}
                <strong>{{ $paginator->lastItem() }}</strong>
            @else
                {{ $paginator->count() }}
            @endif
            {!! __('of') !!}
            <strong>{{ $paginator->total() }}</strong>
            {!! __('results') !!}
        </p>

        <div class="pg-links">
            @if ($paginator->onFirstPage())
                <span class="pg-link pg-disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">&lsaquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pg-link" aria-label="{{ __('pagination.previous') }}">&lsaquo;</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pg-link pg-dots" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pg-link pg-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pg-link" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pg-link" aria-label="{{ __('pagination.next') }}">&rsaquo;</a>
            @else
                <span class="pg-link pg-disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">&rsaquo;</span>
            @endif
        </div>
    </nav>
@endif
