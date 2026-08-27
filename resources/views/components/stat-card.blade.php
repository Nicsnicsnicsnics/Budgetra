@props(['icon', 'color', 'bg', 'label', 'value', 'sub' => null])
<div style="background:var(--bg-white);border-radius:14px;border:1.5px solid var(--border);padding:20px 18px;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
        <div style="width:40px;height:40px;border-radius:10px;background:{{ $bg }};
                    display:flex;align-items:center;justify-content:center;">
            <i class="{{ $icon }}" style="color:{{ $color }};font-size:16px;"></i>
        </div>
        <div style="font-size:12px;color:var(--muted);font-weight:600;">{{ $label }}</div>
    </div>
    <div style="font-size:28px;font-weight:800;margin-bottom:4px;">{{ $value }}</div>
    @if ($sub)
    <div style="font-size:11px;color:var(--muted);">{{ $sub }}</div>
    @endif
</div>
