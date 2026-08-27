<span wire:poll.15s>
    @if ($count > 0)
    <span class="sidebar-badge sidebar-link-label">{{ $count > 99 ? '99+' : $count }}</span>
    @endif
</span>
