@php($categoryId = $values['business_category_id'] ?? null)
<div class="grid md:grid-cols-2 gap-x-6 gap-y-3">
    <div>
        <label for="{{ $key }}-name">Commercial name <span class="text-slate-500 font-normal">(optional)</span></label>
        <input id="{{ $key }}-name" name="{{ $prefix }}name{{ $suffix }}" value="{{ $values['name'] ?? '' }}" maxlength="120" data-business-name @include('components.field-error-attributes', ['field'=>$prefix.'name'.$suffix,'id'=>$key.'-name'])>@include('components.field-error', ['field'=>$prefix.'name'.$suffix,'id'=>$key.'-name'])
    </div>
    <div>
        <label for="{{ $key }}-category">Business category</label>
        <select id="{{ $key }}-category" name="{{ $prefix }}business_category_id{{ $suffix }}" data-business-category @if($categoryRequired)required @endif @include('components.field-error-attributes', ['field'=>$prefix.'business_category_id'.$suffix,'id'=>$key.'-category'])>
            <option value="">Choose category</option>
            @foreach($businessCategories as $category)
                @if($category->is_active || $categoryId == $category->id)
                    <option value="{{ $category->id }}" @if($categoryId == $category->id)selected @endif>{{ $category->name }}{{ $category->is_active ? '' : ' (inactive)' }}</option>
                @endif
            @endforeach
        </select>@include('components.field-error', ['field'=>$prefix.'business_category_id'.$suffix,'id'=>$key.'-category'])
        @if($prefix !== '')
            <label class="sr-only" for="{{ $key }}-category-search">Filter business categories</label>
            <input class="mt-2" id="{{ $key }}-category-search" type="search" placeholder="Filter categories by name" data-option-search="{{ $key }}-category" maxlength="100">
        @endif
    </div>
    <div><label for="{{ $key }}-trade">Line of business</label><input id="{{ $key }}-trade" name="{{ $prefix }}line_of_business{{ $suffix }}" value="{{ $values['line_of_business'] ?? '' }}" maxlength="255" @include('components.field-error-attributes', ['field'=>$prefix.'line_of_business'.$suffix,'id'=>$key.'-trade'])>@include('components.field-error', ['field'=>$prefix.'line_of_business'.$suffix,'id'=>$key.'-trade'])</div>
    <div>
        <label for="{{ $key }}-status">Business status</label>
        <select id="{{ $key }}-status" name="{{ $prefix }}status{{ $suffix }}" data-business-status @include('components.field-error-attributes', ['field'=>$prefix.'status'.$suffix,'id'=>$key.'-status'])>
            @foreach(['active'=>'Active','inactive'=>'Inactive','archived'=>'Archived'] as $value=>$label)
                <option value="{{ $value }}" @if(($values['status'] ?? 'active') === $value)selected @endif>{{ $label }}</option>
            @endforeach
        </select>@include('components.field-error', ['field'=>$prefix.'status'.$suffix,'id'=>$key.'-status'])
    </div>
    <div class="md:col-span-2"><label for="{{ $key }}-goods">Permitted goods or services (optional)</label><textarea id="{{ $key }}-goods" name="{{ $prefix }}permitted_goods{{ $suffix }}" rows="2" maxlength="2000" @include('components.field-error-attributes', ['field'=>$prefix.'permitted_goods'.$suffix,'id'=>$key.'-goods'])>{{ $values['permitted_goods'] ?? '' }}</textarea>@include('components.field-error', ['field'=>$prefix.'permitted_goods'.$suffix,'id'=>$key.'-goods'])</div>
    <div><label for="{{ $key }}-permit">Permit reference (optional)</label><input id="{{ $key }}-permit" name="{{ $prefix }}permit_reference{{ $suffix }}" value="{{ $values['permit_reference'] ?? '' }}" maxlength="255" @include('components.field-error-attributes', ['field'=>$prefix.'permit_reference'.$suffix,'id'=>$key.'-permit'])>@include('components.field-error', ['field'=>$prefix.'permit_reference'.$suffix,'id'=>$key.'-permit'])</div>
    <div><label for="{{ $key }}-registration">Registration reference (optional)</label><input id="{{ $key }}-registration" name="{{ $prefix }}registration_reference{{ $suffix }}" value="{{ $values['registration_reference'] ?? '' }}" maxlength="255" @include('components.field-error-attributes', ['field'=>$prefix.'registration_reference'.$suffix,'id'=>$key.'-registration'])>@include('components.field-error', ['field'=>$prefix.'registration_reference'.$suffix,'id'=>$key.'-registration'])</div>
    <div><label for="{{ $key }}-permit-from">Permit valid from</label><input type="date" id="{{ $key }}-permit-from" name="{{ $prefix }}permit_from{{ $suffix }}" value="{{ $values['permit_from'] ?? '' }}" @include('components.field-error-attributes', ['field'=>$prefix.'permit_from'.$suffix,'id'=>$key.'-permit-from'])>@include('components.field-error', ['field'=>$prefix.'permit_from'.$suffix,'id'=>$key.'-permit-from'])</div>
    <div><label for="{{ $key }}-permit-until">Permit valid until</label><input type="date" id="{{ $key }}-permit-until" name="{{ $prefix }}permit_until{{ $suffix }}" value="{{ $values['permit_until'] ?? '' }}" @include('components.field-error-attributes', ['field'=>$prefix.'permit_until'.$suffix,'id'=>$key.'-permit-until'])>@include('components.field-error', ['field'=>$prefix.'permit_until'.$suffix,'id'=>$key.'-permit-until'])</div>
    <div class="md:col-span-2"><label for="{{ $key }}-remarks">Business remarks</label><textarea id="{{ $key }}-remarks" name="{{ $prefix }}remarks{{ $suffix }}" rows="2" maxlength="2000" @include('components.field-error-attributes', ['field'=>$prefix.'remarks'.$suffix,'id'=>$key.'-remarks'])>{{ $values['remarks'] ?? '' }}</textarea>@include('components.field-error', ['field'=>$prefix.'remarks'.$suffix,'id'=>$key.'-remarks'])</div>
    @if($prefix === '' || !empty($values['category']) || !empty($values['description']))
        <details class="md:col-span-2 border-t border-slate-200 pt-3">
            <summary class="cursor-pointer text-sm font-semibold text-slate-700">Legacy category and description</summary>
            <div class="grid md:grid-cols-2 gap-3 mt-3">
                <div><label for="{{ $key }}-legacy-category">Legacy category text</label><input id="{{ $key }}-legacy-category" name="{{ $prefix }}category{{ $suffix }}" value="{{ $values['category'] ?? '' }}" maxlength="255" @include('components.field-error-attributes', ['field'=>$prefix.'category'.$suffix,'id'=>$key.'-legacy-category'])>@include('components.field-error', ['field'=>$prefix.'category'.$suffix,'id'=>$key.'-legacy-category'])</div>
                <div><label for="{{ $key }}-description">Business description</label><textarea id="{{ $key }}-description" name="{{ $prefix }}description{{ $suffix }}" rows="2" maxlength="2000" @include('components.field-error-attributes', ['field'=>$prefix.'description'.$suffix,'id'=>$key.'-description'])>{{ $values['description'] ?? '' }}</textarea>@include('components.field-error', ['field'=>$prefix.'description'.$suffix,'id'=>$key.'-description'])</div>
            </div>
        </details>
    @endif
</div>
