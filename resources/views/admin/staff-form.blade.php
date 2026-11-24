@php
    $suffix = $u ? (string) $u->id : 'new';
    $errorApplies = old('staff_form') === $suffix;
    $submitted = static function ($field, $default = '') use ($errorApplies) {
        $value = $errorApplies ? old($field, $default) : $default;
        return is_scalar($value) || $value === null ? $value : $default;
    };
    $oldRole = $submitted('role');
    $hasRetryRole = $errorApplies && in_array($oldRole, $roles, true);
    $defaultStatus = $u && !$u->active ? '0' : '1';
    $status = (string) $submitted('active', $defaultStatus);
    if (!in_array($status, ['0', '1'], true)) $status = $defaultStatus;
@endphp
<form method="post" action="{{ $u ? route('staff.update',$u) : route('staff.store') }}">
    @csrf
    @if($u)@method('PUT')@endif
    <div class="grid md:grid-cols-3 gap-4">
        @foreach(['name'=>'Name','email'=>'Email'] as $field => $label)
            @php($id = $field.'-'.$suffix)
            <div>
                <label for="{{ $id }}">{{ $label }}</label>
                <input id="{{ $id }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ $submitted($field, $u ? $u->$field : '') }}" required @include('components.field-error-attributes', compact('field', 'id'))>
                @include('components.field-error', compact('field', 'id'))
            </div>
        @endforeach
        <div>
            <label for="role-{{ $suffix }}">Role</label>
            <select id="role-{{ $suffix }}" name="role" required @include('components.field-error-attributes', ['field'=>'role','id'=>'role-'.$suffix])>
                @if(!$u)<option value="">Choose a role</option>@endif
                @foreach($roles as $role)
                    <option @if($hasRetryRole ? $oldRole === $role : $u && $u->hasRole($role)) selected @endif>{{ $role }}</option>
                @endforeach
            </select>
            @include('components.field-error', ['field'=>'role','id'=>'role-'.$suffix])
        </div>
        <div>
            <label for="password-{{ $suffix }}">{{ $u ? 'New password (optional)' : 'Password' }}</label>
            <input id="password-{{ $suffix }}" name="password" type="password" minlength="12" autocomplete="new-password" @if(!$u)required @endif @include('components.field-error-attributes', ['field'=>'password','id'=>'password-'.$suffix])>
            @include('components.field-error', ['field'=>'password','id'=>'password-'.$suffix])
        </div>
        <div>
            <label for="confirm-{{ $suffix }}">Confirm password</label>
            <input id="confirm-{{ $suffix }}" name="password_confirmation" type="password" autocomplete="new-password">
        </div>
        <div>
            <label for="active-{{ $suffix }}">Account status</label>
            <select id="active-{{ $suffix }}" name="active" @include('components.field-error-attributes', ['field'=>'active','id'=>'active-'.$suffix])>
                <option value="1">Active</option>
                <option value="0" @if($status === '0') selected @endif>Disabled</option>
            </select>
            @include('components.field-error', ['field'=>'active','id'=>'active-'.$suffix])
        </div>
    </div>
    <button class="btn mt-5" type="submit">Save account</button>
</form>
