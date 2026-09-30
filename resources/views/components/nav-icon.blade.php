@props(['icon', 'style' => ''])
{{-- One icon slot, two kinds of icon.

     A value starting with "fa-" is a Font Awesome class and is drawn as a
     glyph, which is how the chevron, the search magnifier and the profile
     row still work. Anything else is the basename of a PNG in
     public/systemicons, painted through .app-icon's mask.

     Keeping both behind one tag means a row can move between the two by
     changing a single array value, and nothing downstream has to know. --}}
@if (str_starts_with($icon, 'fa-'))
<i class="{{ $icon }}"@if ($style) style="{{ $style }}"@endif></i>
@else
<i class="app-icon" style="{{ system_icon($icon) }}{{ $style ? ';' . $style : '' }}"></i>
@endif
