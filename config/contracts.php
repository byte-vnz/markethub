<?php

return [
    'source_path' => storage_path('app/private/references/v02_San_Pedro_Public_Market_Lease_Template.docx'),
    'pdf_executable' => env('CONTRACT_PDF_EXECUTABLE'),
    'pdf_timeout' => (int) env('CONTRACT_PDF_TIMEOUT', 60),
];
