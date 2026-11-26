<fieldset class="mt-4">
    <legend class="font-semibold">Concurrent assignment authorization</legend>
    <p class="text-sm text-slate-600">A reason and an approved policy permitting concurrent assignments are required when another active assignment overlaps the new term.</p>
    <label for="concurrent_authorization_reference">Authorization reference (optional)</label>
    <input id="concurrent_authorization_reference" name="concurrent_authorization_reference" value="{{ old('concurrent_authorization_reference') }}" maxlength="120">
    <label for="concurrent_authorization_reason">Authorization reason</label>
    <textarea id="concurrent_authorization_reason" name="concurrent_authorization_reason" minlength="5" maxlength="1000">{{ old('concurrent_authorization_reason') }}</textarea>
</fieldset>
