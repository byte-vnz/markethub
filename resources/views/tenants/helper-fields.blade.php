@foreach([0, 1] as $slot)
    @php($helper = $helperValues[$slot] ?? [])
    <fieldset class="mt-3 border-t border-slate-200 pt-3">
        <legend class="font-semibold text-sm">Helper {{ $slot + 1 }}</legend>
        <div class="grid md:grid-cols-2 gap-x-6 gap-y-3">
            @foreach(['full_name'=>'Full name','relationship'=>'Relationship','phone'=>'Contact number'] as $field=>$label)
                <div><label for="{{ $key }}-{{ $slot }}-{{ $field }}">{{ $label }}</label><input id="{{ $key }}-{{ $slot }}-{{ $field }}" name="{{ $prefix }}[{{ $slot }}][{{ $field }}]" value="{{ $helper[$field] ?? '' }}" maxlength="{{ ['full_name'=>150,'relationship'=>100,'phone'=>40][$field] }}" type="{{ $field==='phone' ? 'tel' : 'text' }}" @include('components.field-error-attributes', ['field'=>$prefix.'['.$slot.']['.$field.']','id'=>$key.'-'.$slot.'-'.$field])>@include('components.field-error', ['field'=>$prefix.'['.$slot.']['.$field.']','id'=>$key.'-'.$slot.'-'.$field])</div>
            @endforeach
            <div><label for="{{ $key }}-{{ $slot }}-status">Registration status</label><select id="{{ $key }}-{{ $slot }}-status" name="{{ $prefix }}[{{ $slot }}][status]" @include('components.field-error-attributes', ['field'=>$prefix.'['.$slot.'][status]','id'=>$key.'-'.$slot.'-status'])>@foreach(['authorized'=>'Registered and authorized','inactive'=>'Inactive'] as $value=>$label)<option value="{{ $value }}" @if(($helper['status'] ?? 'authorized') === $value)selected @endif>{{ $label }}</option>@endforeach</select>@include('components.field-error', ['field'=>$prefix.'['.$slot.'][status]','id'=>$key.'-'.$slot.'-status'])</div>
            <div class="md:col-span-2"><label for="{{ $key }}-{{ $slot }}-remarks">Remarks</label><textarea id="{{ $key }}-{{ $slot }}-remarks" name="{{ $prefix }}[{{ $slot }}][remarks]" rows="2" maxlength="2000" @include('components.field-error-attributes', ['field'=>$prefix.'['.$slot.'][remarks]','id'=>$key.'-'.$slot.'-remarks'])>{{ $helper['remarks'] ?? '' }}</textarea>@include('components.field-error', ['field'=>$prefix.'['.$slot.'][remarks]','id'=>$key.'-'.$slot.'-remarks'])</div>
        </div>
    </fieldset>
@endforeach
