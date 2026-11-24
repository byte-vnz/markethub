<details class="border-t border-slate-200 py-4" data-assignment-helpers="{{ $assignmentKey }}">
    <summary class="cursor-pointer text-sm font-semibold text-slate-700" data-helper-heading>Helpers for this stall</summary>
    @include('tenants.helper-fields', ['key'=>'pending-helpers-'.$assignmentKey, 'prefix'=>'pending_assignments['.$assignmentKey.'][helpers]', 'helperValues'=>$selection['helpers'] ?? []])
</details>
