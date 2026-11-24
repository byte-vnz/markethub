@extends('layouts.app')
@section('title','Staff Management')
@section('subtitle','Named accounts and the approved role matrix. Passwords require at least 12 characters.')
@section('content')
<section class="panel">
    <h2>Create staff account</h2>
    @include('admin.staff-form',['u'=>null])
</section>
<section class="panel mt-5">
    <h2>Existing accounts</h2>
    @foreach($users as $u)
        <details class="border-b py-4" id="staff-account-{{ $u->id }}" @if(old('staff_form') === (string) $u->id && $errors->any())open @endif>
            <summary class="cursor-pointer break-words">{{ $u->name }} · {{ $u->email }} · {{ $u->active?'Active':'Disabled' }} · {{ $u->getRoleNames()->join(', ') }}</summary>
            @include('admin.staff-form')
        </details>
    @endforeach
    <div class="mt-5">{{ $users->links() }}</div>
</section>
@endsection
