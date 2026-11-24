@extends('layouts.app')
@section('title',$business->exists ? 'Edit business' : 'Register business')
@section('subtitle',$tenant->name.' · '.$tenant->reference)
@section('content')
<section class="panel max-w-4xl">
    <h2>Business information</h2>
    @if($business->exists)
        <p class="text-sm text-slate-600 mt-2">Existing assessments and contracts retain the recorded trade and category. Use an authorized renewal to apply changes to new terms.</p>
    @endif
    <form class="mt-4" method="post" action="{{ $business->exists ? route('tenant-businesses.update',[$tenant,$business]) : route('tenant-businesses.store',$tenant) }}">
        @csrf
        @if($business->exists)@method('PUT')@endif
        @include('tenants.business-fields', ['key'=>'tenant-business','values'=>array_replace($business->getAttributes(), collect(old())->filter(function ($value) { return is_scalar($value) || $value === null; })->all()),'prefix'=>'','suffix'=>'','categoryRequired'=>!$business->exists])
        <div class="flex flex-wrap gap-3 mt-5">
            <button type="submit" class="btn">{{ $business->exists ? 'Save business' : 'Register business' }}</button>
            <a class="btn secondary" href="{{ route('records.show',['tenants',$tenant->id,'tab'=>'businesses']) }}">Cancel</a>
        </div>
    </form>
</section>
@endsection
