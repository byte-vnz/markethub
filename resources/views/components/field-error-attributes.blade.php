@php
    $field = strtr($field, ['['=>'.', ']'=>'']);
    $invalid = ($errorApplies ?? true) && $errors->has($field);
    $description = trim(($hint ?? '').($invalid ? ' '.$id.'-error' : ''));
    if ($invalid) {
        $__env->startPush('error-target-'.$field, $id);
    }
@endphp
@if($invalid) aria-invalid="true" @endif
@if($description !== '') aria-describedby="{{ $description }}" @endif
