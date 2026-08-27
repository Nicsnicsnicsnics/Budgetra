{{-- Generic admin dialog shell. Pages open it the same way every other
     admin modal opens — document.getElementById(id).style.display = 'flex'
     — so the shared Escape / scroll-lock handling in layouts.admin picks it
     up too. It previously reached for .adm-modal classes that no stylesheet
     ever defined, which left it unstyled. --}}
@props(['id'])
<div id="{{ $id }}" class="admin-modal-backdrop" style="display:none;"
     onclick="if(event.target===this)this.style.display='none';">
    <div class="admin-modal-card">
        {{ $slot }}
    </div>
</div>
