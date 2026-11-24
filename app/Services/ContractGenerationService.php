<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

class ContractGenerationService
{
    private $templates;

    public function __construct(ContractTemplateService $templates)
    {
        $this->templates = $templates;
    }

    public function render(string $templatePath, array $snapshot, string $outputPath): void
    {
        if (!in_array($snapshot['civil_status'] ?? null, ['Single', 'Married'], true)) {
            throw new InvalidArgumentException('The template supports Single or Married only; another status requires an approved template mapping.');
        }
        $snapshot['civil_single'] = $snapshot['civil_status'] === 'Single' ? 'X ' : '  ';
        $snapshot['civil_married'] = $snapshot['civil_status'] === 'Married' ? 'X ' : '  ';
        $snapshot['lessee_identity'] = trim(($snapshot['government_id_type'] ?? '').' / '.($snapshot['government_id_number'] ?? ''), ' /');
        $snapshot['lessor_identity'] = trim(($snapshot['lessor_id_type'] ?? '').' / '.($snapshot['lessor_id_number'] ?? ''), ' /');
        $snapshot['lessor_id_date_place_issued'] = $snapshot['lessor_id_date_place_issued'] ?? '';
        $xml = $this->templates->readDocument($templatePath);
        $xml = preg_replace_callback('/\$\{([a-z_0-9]+)\}/', function ($match) use ($snapshot) {
            $key = $match[1];
            if (!array_key_exists($key, $snapshot) || (!is_string($snapshot[$key]) && !is_int($snapshot[$key]))) {
                throw new InvalidArgumentException('A required contract snapshot field is missing: '.$key.'.');
            }
            $value = (string) $snapshot[$key];
            if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]|\$\{/', $value) || mb_strlen($value) > 1000) {
                throw new InvalidArgumentException('A contract snapshot field contains unsupported characters or exceeds the review limit: '.$key.'.');
            }
            return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }, $xml);
        if (strpos($xml, '${') !== false) {
            throw new RuntimeException('The contract contains an unresolved placeholder.');
        }
        $this->templates->writeCopy($templatePath, $outputPath, $xml);
    }
}
