@if($section && preg_match('/\A#[0-9a-fA-F]{6}\z/', $section->color ?? ''))
    <span aria-hidden="true" class="inline-block w-3 h-3 rounded-full border border-slate-400 shrink-0" style="background-color:{{ $section->color }}"></span>
@endif
