@props(['id'])
<div id="{{ $id }}" class="adm-modal-backdrop" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="adm-modal">
        {{ $slot }}
    </div>
</div>
