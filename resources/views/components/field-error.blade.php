@php($field = strtr($field, ['['=>'.', ']'=>'']))
@if(($errorApplies ?? true) && $errors->has($field))
    <p id="{{ $id }}-error" class="text-sm text-red-800 mt-1">{{ $errors->first($field) }}</p>
@endif
