<div style="text-align:center; margin-bottom:1.5rem; padding:1rem 0;">
    @if($setting->logo_path)
        <img src="{{ Storage::disk('public')->url($setting->logo_path) }}"
             style="height:64px; margin:0 auto 0.75rem; display:block;" alt="Logo">
    @endif
    <h1 style="font-size:1.25rem; font-weight:700; color:#1f2937;">{{ $setting->institution_name }}</h1>
    @if($setting->short_name)
        <p style="font-size:0.875rem; color:#6b7280;">{{ $setting->short_name }}</p>
    @endif
</div>
