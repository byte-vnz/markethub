<fieldset class="border-t border-slate-200 py-4" data-registration-business="{{ $businessKey }}">
    <legend class="font-semibold text-sm" data-business-heading>{{ $values['name'] ?? 'Business details' }}</legend>
    @if(!empty($values['id']))
        <input type="hidden" name="businesses[{{ $businessKey }}][id]" value="{{ $values['id'] }}" data-registration-business-id>
    @endif
    @include('tenants.business-fields', ['key'=>'business-'.$businessKey,'prefix'=>'businesses['.$businessKey.'][','suffix'=>']','categoryRequired'=>true])
    @if(empty($values['id']))
        <button type="button" class="btn secondary mt-3" data-remove-business>Remove business</button>
    @endif
</fieldset>
