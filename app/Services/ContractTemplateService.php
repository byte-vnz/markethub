<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class ContractTemplateService
{
    const SOURCE_SHA256 = '38eb9c85d161d98a7f32f213efbefa3b810e682b72d1a9d4352a70cc13b014fb';
    const V04_SHA256 = 'd1963eab708eefc59fcd686b8ad16fe5647bd0a12311f496cb60add7c0f454f0';

    const V04_FIELDS = [
        8 => 'lessee_full_name', 13 => 'citizenship', 15 => 'residential_address',
        18 => 'contact_number', 20 => 'government_id_type', 22 => 'government_id_number',
        25 => 'id_date_place_issued', 27 => 'helper_1', 29 => 'helper_2',
        33 => 'stall_code', 36 => 'stall_category', 39 => 'floor_area',
        42 => 'permitted_goods', 45 => 'rental_rate_sqm', 48 => 'daily_rental',
        50 => 'advance_rental', 52 => 'security_deposit', 54 => 'monthly_rental',
        57 => 'lease_period', 210 => 'lessor_identity', 211 => 'lessor_id_date_place_issued',
        212 => 'lessee_full_name', 213 => 'lessee_identity', 214 => 'id_date_place_issued',
    ];

    // Zero-based paragraph locations in the reviewed, immutable v02 source only.
    const FIELDS = [
        8 => 'lessee_full_name', 13 => 'citizenship', 15 => 'residential_address',
        18 => 'contact_number', 20 => 'government_id_type', 22 => 'government_id_number',
        25 => 'id_date_place_issued', 27 => 'helper_1', 29 => 'helper_2',
        33 => 'stall_code', 36 => 'stall_category', 39 => 'floor_area', 41 => 'lease_period',
        43 => 'permitted_goods', 46 => 'rental_rate_sqm', 49 => 'daily_rental',
        51 => 'daily_cusa', 53 => 'billing_basis', 55 => 'total_monthly_rental_cusa',
        57 => 'advance_rental', 59 => 'security_deposit', 61 => 'total_initial_payment',
        213 => 'lessor_identity', 214 => 'lessor_id_date_place_issued',
        215 => 'lessee_full_name', 216 => 'lessee_identity', 217 => 'id_date_place_issued',
    ];

    public function prepare(string $sourcePath, string $targetPath): array
    {
        $sourceHash = is_file($sourcePath) ? hash_file('sha256', $sourcePath) : false;
        if (!in_array($sourceHash, [self::SOURCE_SHA256, self::V04_SHA256], true)) {
            throw new RuntimeException('The contract source differs from the reviewed v02/v04 templates. Review and map a new version first.');
        }
        $v04 = $sourceHash === self::V04_SHA256;
        $fields = $v04 ? self::V04_FIELDS : self::FIELDS;
        $xml = $this->readDocument($sourcePath);
        $this->validateXml($xml);
        $xml = $this->editParagraphs($xml, function ($paragraph, $index) use ($v04, $fields) {
            if (isset($fields[$index])) {
                return $this->setValue($paragraph, '${'.$fields[$index].'}');
            }
            if ($index === 2) {
                return str_replace('SPPM-2026-11-01-C', '${contract_control_no}', $paragraph);
            }
            if ($v04 && $index === 40) {
                preg_match('/<w:pPr>.*?(<w:rPr>.*?<\/w:rPr>).*?<\/w:pPr>/s', $paragraph, $properties);
                $run = '<w:r>'.($properties[1] ?? '').'<w:t xml:space="preserve">: ${stall_location}</w:t></w:r>';
                return str_replace('</w:p>', $run.'</w:p>', $paragraph);
            }
            if ($index === 11) {
                return $this->setValue($paragraph, '[${civil_single}] Single  [${civil_married}] Married  ${gender}');
            }
            if ($index === ($v04 ? 61 : 64)) {
                return str_replace('_____ day of _____________, 2026', '${execution_day} day of ${execution_month}, ${execution_year}', $paragraph);
            }
            if (in_array($index, $v04 ? [5, 65, 209] : [5, 68, 212], true)) {
                return str_replace('HON. ART JOSEPH FRANCIS MERCADO', '${lessor_name}', $paragraph);
            }
            if ($index === ($v04 ? 79 : 82)) {
                return str_replace(['SPPM-2026', '-11-01-C'], ['${contract_control_no}', ''], $paragraph);
            }
            // Fixed sample notarial years are variable fields, not verified execution facts.
            if ($index === ($v04 ? 204 : 207)) {
                return str_replace(', 2026, personally appeared:', ', _____, personally appeared:', $paragraph);
            }
            if ($index === ($v04 ? 220 : 223)) {
                return str_replace('Series of 2026.', 'Series of _____.', $paragraph);
            }
            return $paragraph;
        });
        $this->writeCopy($sourcePath, $targetPath, $xml);
        preg_match_all('/\$\{([a-z_0-9]+)\}/', $xml, $matches);
        return ['source_checksum' => $sourceHash, 'checksum' => hash_file('sha256', $targetPath), 'placeholders' => array_values(array_unique($matches[1]))];
    }

    public function readDocument(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open the private DOCX document.');
        }
        try {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false || strlen($xml) > 5000000) {
                throw new RuntimeException('Invalid or oversized DOCX document XML.');
            }
            return $xml;
        } finally {
            $zip->close();
        }
    }

    public function validateXml(string $xml): void
    {
        $prior = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            if (stripos($xml, '<!DOCTYPE') !== false || !$document->loadXML($xml, LIBXML_NONET)) {
                throw new RuntimeException('Invalid contract document XML.');
            }
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            if ($xpath->query('//w:tbl')->length !== 5 || $xpath->query('//w:gridSpan')->length !== 16 || !in_array($xpath->query('//w:p')->length, [221, 224], true)) {
                throw new RuntimeException('The contract document structure differs from the reviewed template.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prior);
        }
    }

    public function writeCopy(string $source, string $target, string $xml): void
    {
        $this->validateXml($xml);
        if (file_exists($target) || !is_dir(dirname($target))) {
            throw new RuntimeException('Contract destination must be a new file inside an existing private directory.');
        }
        $handle = fopen($target, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to reserve the contract destination.');
        }
        $input = null;
        $zip = new ZipArchive();
        $opened = false;
        try {
            $input = fopen($source, 'rb');
            if ($input === false || stream_copy_to_stream($input, $handle) === false) {
                throw new RuntimeException('Unable to copy the contract source.');
            }
            fclose($handle);
            $opened = $zip->open($target) === true;
            if (!$opened || !$zip->addFromString('word/document.xml', $xml)) {
                throw new RuntimeException('Unable to write the contract document.');
            }
            $closed = $zip->close();
            $opened = false;
            if (!$closed) {
                throw new RuntimeException('Unable to finish the contract document.');
            }
        } catch (\Throwable $exception) {
            if ($opened) {
                $zip->close();
            }
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($target)) {
                unlink($target);
            }
            throw $exception;
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
        }
    }

    private function editParagraphs(string $xml, callable $edit): string
    {
        $index = 0;
        return preg_replace_callback('/<w:p\b[^>]*?(?:\/>|>.*?<\/w:p>)/s', function ($match) use ($edit, &$index) {
            return $edit($match[0], $index++);
        }, $xml);
    }

    private function setValue(string $paragraph, string $value): string
    {
        $value = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        if (preg_match('/<w:t\b[^>]*>/', $paragraph)) {
            return preg_replace_callback('/(<w:t\b[^>]*>).*?(<\/w:t>)/s', function ($match) use (&$value) {
                $replacement = $match[1].$value.$match[2];
                $value = '';
                return $replacement;
            }, $paragraph);
        }
        // Empty source fields have paragraph run properties but no text run to fill.
        preg_match('/<w:pPr>.*?(<w:rPr>.*?<\/w:rPr>).*?<\/w:pPr>/s', $paragraph, $properties);
        $run = '<w:r>'.($properties[1] ?? '').'<w:t xml:space="preserve">'.$value.'</w:t></w:r>';
        return str_replace('</w:p>', $run.'</w:p>', $paragraph);
    }
}
