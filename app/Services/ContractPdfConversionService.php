<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ContractPdfConversionService
{
    public function convert(string $docxPath, string $outputDir): ?string
    {
        $executable = config('contracts.pdf_executable');
        if (!$executable || !is_file($executable)) {
            return null;
        }
        $temporary = storage_path('app/private/contract-conversion/'.bin2hex(random_bytes(16)));
        $process = null;
        try {
            if (!is_file($docxPath) || !is_dir($outputDir)) {
                throw new \RuntimeException('Missing private conversion input or destination.');
            }
            File::makeDirectory($temporary.'/profile', 0700, true);
            File::makeDirectory($temporary.'/output', 0700, true);
            if (!copy($docxPath, $temporary.'/contract.docx')) {
                throw new \RuntimeException('Unable to stage the DOCX.');
            }
            $profile = 'file:///'.ltrim(str_replace('\\', '/', $temporary.'/profile'), '/');
            $profile = str_replace(['%', ' ', '#'], ['%25', '%20', '%23'], $profile);
            $process = new Process([
                $executable, '-env:UserInstallation='.$profile, '--headless', '--nologo',
                '--nodefault', '--nolockcheck', '--norestore', '--convert-to', 'pdf:writer_pdf_Export',
                '--outdir', $temporary.'/output', $temporary.'/contract.docx',
            ]);
            $process->setTimeout(max(1, min(300, (int) config('contracts.pdf_timeout', 60))));
            $process->run();
            $pdf = $temporary.'/output/contract.pdf';
            if (!$process->isSuccessful() || !is_file($pdf) || file_get_contents($pdf, false, null, 0, 5) !== '%PDF-') {
                throw new \RuntimeException('The converter did not produce a PDF.');
            }
            $target = rtrim($outputDir, '/\\').'/contract-'.bin2hex(random_bytes(16)).'.pdf';
            if (!rename($pdf, $target)) {
                throw new \RuntimeException('Unable to preserve the PDF.');
            }
            return $target;
        } catch (\Throwable $exception) {
            // Process output/exception messages can contain names or document content.
            Log::warning('Contract PDF conversion failed; DOCX remains available.', ['failure_type' => get_class($exception)]);
            return null;
        } finally {
            if ($process && $process->isRunning()) {
                $process->stop(1);
            }
            if (is_dir($temporary)) {
                File::deleteDirectory($temporary);
            }
        }
    }
}
