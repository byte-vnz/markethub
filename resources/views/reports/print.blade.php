<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{{ $title }}</title><style>body{font-family:DejaVu Sans,Arial,sans-serif;font-size:10px;color:#17253b}h1{font-size:20px}.record-detail{margin-bottom:18px;page-break-inside:avoid}.record-detail h2{font-size:13px}.record-detail th{font-weight:600}.record-detail td,.record-detail th{padding:5px 7px}table{width:100%;border-collapse:collapse;table-layout:fixed}td,th{padding:7px;border-bottom:1px solid #ddd;text-align:left;overflow-wrap:break-word}th{background:#eef2f7}thead{display:table-header-group}tr{page-break-inside:avoid}@media print{button{display:none}}</style></head><body><h1>MarketHub · {{ $title }}</h1><p>Generated {{ now()->format('Y-m-d H:i') }} Asia/Manila. {{ $reportNote ?? 'Current records. Official receipts are issued through the Treasury workflow.' }}</p>@if(count($columns)>12)
@foreach($rows as $row)
<section class="record-detail"><h2>{{ ucwords(str_replace('_',' ',$columns[0])) }}: {{ ((array)$row)[$columns[0]] }}</h2>
<table><tbody>@foreach(array_chunk((array)$row,2,true) as $fields)<tr>@foreach($fields as $field=>$value)<th style="width:20%">{{ ucwords(str_replace('_',' ',$field)) }}</th><td style="width:30%">{{ $value ?? '—' }}</td>@endforeach</tr>@endforeach</tbody></table>
</section>
@endforeach
@else
<table><thead><tr>@foreach($columns as $column)<th>{{ ucwords(str_replace('_',' ',$column)) }}</th>@endforeach</tr></thead><tbody>@forelse($rows as $row)<tr>@foreach((array)$row as $value)<td>{{ $value ?? '—' }}</td>@endforeach</tr>@empty<tr><td>No matching records.</td></tr>@endforelse</tbody></table>
@endif
</body></html>
