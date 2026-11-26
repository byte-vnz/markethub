<?php
$activeFilename = 'v04_San_Pedro_Public_Market_Lease_Template.docx';
return [
    'active_template_filename' => $activeFilename,
    'source_path' => storage_path('app/private/references/'.$activeFilename),
    'pdf_executable' => env('CONTRACT_PDF_EXECUTABLE'),
    'pdf_timeout' => (int) env('CONTRACT_PDF_TIMEOUT', 60),
];
