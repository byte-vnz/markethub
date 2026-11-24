<?php

namespace Tests\Feature;

use App\Services\ContractPdfConversionService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ContractPdfConversionTest extends TestCase
{
    public function test_unconfigured_converter_returns_explicit_docx_fallback(): void
    {
        config(['contracts.pdf_executable' => null]);
        $this->assertNull((new ContractPdfConversionService())->convert('unused.docx', 'unused'));
    }

    public function test_converter_failure_cleans_private_temporary_files_without_logging_contents(): void
    {
        config(['contracts.pdf_executable' => PHP_BINARY, 'contracts.pdf_timeout' => 5]);
        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'Contract PDF conversion failed; DOCX remains available.' && array_keys($context) === ['failure_type'];
        });
        $before = glob(storage_path('app/private/contract-conversion/*')) ?: [];
        $directory = storage_path('app/private/pdf-test-'.bin2hex(random_bytes(8)));
        File::makeDirectory($directory, 0700, true);
        try {
            file_put_contents($directory.'/synthetic.docx', 'synthetic content');
            $this->assertNull((new ContractPdfConversionService())->convert($directory.'/synthetic.docx', $directory));
            $this->assertSame($before, glob(storage_path('app/private/contract-conversion/*')) ?: []);
            $this->assertSame(['synthetic.docx'], array_map('basename', glob($directory.'/*')));
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
