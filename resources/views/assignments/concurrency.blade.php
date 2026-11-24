<fieldset class="mt-4">
    <legend class="font-semibold">Concurrent assignment authorization</legend>
    <p class="text-sm text-slate-600">Required when the tenant has another active assignment that overlaps the new term.</p>
    <label for="concurrent_authorization_reference">Authorization reference</label>
    <input id="concurrent_authorization_reference" name="concurrent_authorization_reference" value="{{ old('concurrent_authorization_reference') }}" maxlength="120">
    <label for="concurrent_authorization_reason">Authorization reason</label>
    <textarea id="concurrent_authorization_reason" name="concurrent_authorization_reason" minlength="5" maxlength="1000">{{ old('concurrent_authorization_reason') }}</textarea>
</fieldset>
