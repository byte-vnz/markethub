@if($quote)
    <dl class="mt-2">
        <div class="detailrow"><dt>Automatic daily rent</dt><dd>PHP {{ $quote['automatic_daily_rental'] }}</dd></div>
        <div class="detailrow"><dt>Applicable daily rent</dt><dd>PHP {{ $quote['daily_rental'] }}</dd></div>
        <div class="detailrow"><dt>Daily difference from automatic</dt><dd>PHP {{ $quote['rental_difference'] }}</dd></div>
        <div class="detailrow"><dt>Rental computation</dt><dd>
            @if($quote['computation_mode'] === 'manual_override')
                Manual override<br><span class="badge status-occupied">Approved override applies</span>
            @else Automatic @endif
        </dd></div>
        <div class="detailrow"><dt>Monthly rent / CUSA</dt><dd>PHP {{ $quote['monthly_rental'] }} / {{ $quote['monthly_cusa'] }}</dd></div>
        <div class="detailrow"><dt>Monthly total · {{ $quote['billing_days'] }} days</dt><dd>PHP {{ $quote['total_monthly_rental_cusa'] }}</dd></div>
    </dl>
@elseif($quoteError)
    <p class="text-sm text-red-800 mt-2">{{ $quoteError }}</p>
@else
    <p class="text-sm text-slate-600 mt-2">An applicable daily rent is unavailable for this assignment and reference month.</p>
@endif
