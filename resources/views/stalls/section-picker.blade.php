<div data-section-picker data-options-url="{{ route('stalls.section-options',['market'=>'MARKET_ID']) }}">
    <select id="market_section_id" name="market_section_id" aria-required="true" @include('components.field-error-attributes', ['field'=>'market_section_id','id'=>'market_section_id','hint'=>'stall-section-feedback'])>
        <option value="">Choose a section</option>
        @foreach($options['market_section_id'] as $section)
            <option value="{{ $section->id }}" @if($selectedSectionId == $section->id) selected @endif>{{ $section->name }} · {{ $section->code }}</option>
        @endforeach
        @if($retainedSection && $retainedSection->market_id == old('market_id',$record->market_id))
            <option data-retained-market="{{ $retainedSection->market_id }}" value="{{ $retainedSection->id }}" @if($selectedSectionId == $retainedSection->id) selected @endif>{{ $retainedSection->name }} · {{ $retainedSection->code }} (Inactive · Retained)</option>
        @endif
    </select>
    <div data-section-search-wrap hidden>
        <label for="search_sections">Search sections</label>
        <input type="search" id="search_sections" placeholder="Section name or code" autocomplete="off" aria-controls="market_section_id">
    </div>
    <p id="stall-section-feedback" class="text-xs text-slate-600 mt-2" role="status"></p>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm mt-1">
        <button type="button" class="text-blue-800 underline py-2" data-refresh-sections hidden>Refresh sections</button>
        @can('sections.manage')
            <a class="text-blue-800 underline py-2" data-add-section data-create-url="{{ route('records.create','sections') }}" target="_blank" rel="noopener"
               href="{{ route('records.create',['type'=>'sections','market_id'=>old('market_id',$record->market_id),'from_stall'=>1]) }}">Add section in new tab</a>
        @endcan
    </div>
</div>
