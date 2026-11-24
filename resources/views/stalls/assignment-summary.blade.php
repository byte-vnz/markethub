@if($assignment)
<section class="panel mt-5 max-w-4xl"><h2>Current assignment</h2><dl>
@can('tenants.view')<div class="detailrow"><dt>Current tenant / business</dt><dd><a class="text-blue-800 underline" href="{{ route('records.show',['tenants',$assignment->tenant_id]) }}">{{ $assignment->tenant->name }}</a><br>{{ optional($assignment->business)->name ?: 'Individual stallholder' }}</dd></div><div class="detailrow"><dt>Current business category</dt><dd>{{ $assignment->business ? optional($assignment->business->businessCategory)->name : 'Business not linked' }}</dd></div>@endcan
@can('billing.view')
@php($assessment=$assignment->agreement ? $assignment->agreement->assessment : null)
@if($assessment)
@foreach(['period'=>'Assessment reference month','business_category_name'=>'Assessed business category','location_type'=>'Assessed location','floor_area'=>'Assessed area (sq. m.)','rental_rate_sqm'=>'Initial assessed daily rate per sq. m. (PHP)','daily_rental'=>'Initial assessed daily base rent (PHP)','monthly_rental'=>'Reference monthly base rent (PHP)','monthly_cusa'=>'Reference monthly CUSA (PHP)','total_monthly_rental_cusa'=>'Reference monthly total (PHP)'] as $key=>$label)<div class="detailrow"><dt>{{ $label }}</dt><dd>{{ $assessment->$key }}</dd></div>@endforeach
<p class="text-xs text-slate-600 mt-3">These values come from the initial assessment.</p>
@can('view',$assignment->tenant)
<a class="text-blue-800 underline" href="{{ route('records.show',['tenants',$assignment->tenant_id,'tab'=>'rental']) }}">View Rental Information</a>
@endcan
@elseif($assignment->agreement)<div class="detailrow"><dt>Legacy monthly agreement</dt><dd>PHP {{ $assignment->agreement->monthly_amount }}</dd></div>@endif
@endcan
</dl></section>
@endif
