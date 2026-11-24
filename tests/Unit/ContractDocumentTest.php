<?php

namespace Tests\Unit;

use App\Services\ContractGenerationService;
use App\Services\ContractTemplateService;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ContractDocumentTest extends TestCase
{
    private $directory;
    private $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/markethub-contract-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->source = dirname(__DIR__, 2).'/storage/app/private/references/v02_San_Pedro_Public_Market_Lease_Template.docx';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_only_designated_document_xml_changes_and_all_legal_paragraphs_remain_identical(): void
    {
        $templates = new ContractTemplateService();
        $prepared = $this->directory.'/prepared.docx';
        $metadata = $templates->prepare($this->source, $prepared);
        $output = $this->directory.'/generated.docx';
        (new ContractGenerationService($templates))->render($prepared, $this->snapshot($metadata['placeholders']), $output);
        $original = new ZipArchive();
        $generated = new ZipArchive();
        $this->assertTrue($original->open($this->source));
        $this->assertTrue($generated->open($output));
        $this->assertSame($original->numFiles, $generated->numFiles);
        for ($i = 0; $i < $original->numFiles; $i++) {
            $name = $original->getNameIndex($i);
            if ($name !== 'word/document.xml') {
                $this->assertSame($original->getFromName($name), $generated->getFromName($name), $name);
            }
        }
        $originalXml = $original->getFromName('word/document.xml');
        $generatedXml = $generated->getFromName('word/document.xml');
        $original->close();
        $generated->close();
        preg_match_all('/<w:p\b[^>]*?(?:\/>|>.*?<\/w:p>)/s', $originalXml, $originalParagraphs);
        preg_match_all('/<w:p\b[^>]*?(?:\/>|>.*?<\/w:p>)/s', $generatedXml, $generatedParagraphs);
        for ($index = 84; $index <= 201; $index++) {
            $this->assertSame($originalParagraphs[0][$index], $generatedParagraphs[0][$index], 'Legal/signature paragraph '.$index);
        }
        $this->assertSame(ContractTemplateService::SOURCE_SHA256, hash_file('sha256', $this->source));
        $this->assertStringNotContainsString('${', $generatedXml);
        $this->assertStringNotContainsString('SPPM-2026-11-01-C', $generatedXml);
        $this->assertStringNotContainsString('November 1, 2026', $generatedXml);
        $this->assertStringContainsString('[X ] Single  [  ] Married  Female', $generatedXml);
        $this->assertSame(2, substr_count($generatedXml, 'TEST-2026-000001'));
        $this->assertStringContainsString('Series of _____.', $generatedXml);
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($generatedXml, LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        foreach (['tbl' => 5, 'gridSpan' => 16, 'p' => 224] as $element => $count) {
            $this->assertSame($count, $xpath->query('//w:'.$element)->length);
        }
        $this->assertStringContainsString('SYNTHETIC &amp; &lt;LESSEE&gt;', $generatedXml);
        $this->assertStringContainsString('Test ID / TEST-ID-001', $generatedXml);
    }

    public function test_unknown_source_is_rejected_before_creating_a_working_copy(): void
    {
        $source = $this->directory.'/unknown.docx';
        file_put_contents($source, 'unknown');
        $this->expectException(\RuntimeException::class);
        (new ContractTemplateService())->prepare($source, $this->directory.'/output.docx');
    }

    public function test_long_values_and_two_helpers_are_preserved_without_truncation_for_visual_review(): void
    {
        $templates = new ContractTemplateService();
        $prepared = $this->directory.'/prepared.docx';
        $metadata = $templates->prepare($this->source, $prepared);
        $snapshot = $this->snapshot($metadata['placeholders']);
        $snapshot['lessee_full_name'] = str_repeat('SYNTHETIC LONG NAME ', 8);
        $snapshot['residential_address'] = str_repeat('Synthetic long address, ', 12);
        $snapshot['civil_status'] = 'Married';
        (new ContractGenerationService($templates))->render($prepared, $snapshot, $this->directory.'/generated.docx');
        $xml = $templates->readDocument($this->directory.'/generated.docx');
        foreach (['lessee_full_name', 'residential_address', 'helper_1', 'helper_2'] as $field) {
            $this->assertTrue(strpos($xml, $snapshot[$field]) !== false, 'Untruncated '.$field);
        }
        $this->assertTrue(strpos($xml, '[  ] Single  [X ] Married') !== false);
    }

    public function test_existing_output_is_never_overwritten(): void
    {
        $output = $this->directory.'/output.docx';
        file_put_contents($output, 'immutable');
        try {
            (new ContractTemplateService())->prepare($this->source, $output);
            $this->fail('Existing document was not protected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('immutable', file_get_contents($output));
        }
    }

    /** @dataProvider invalidSnapshots */
    public function test_invalid_or_incomplete_snapshots_are_rejected(string $field, $value): void
    {
        $templates = new ContractTemplateService();
        $prepared = $this->directory.'/prepared.docx';
        $metadata = $templates->prepare($this->source, $prepared);
        $snapshot = $this->snapshot($metadata['placeholders']);
        if ($value === null) {
            unset($snapshot[$field]);
        } else {
            $snapshot[$field] = $value;
        }
        $this->expectException(\InvalidArgumentException::class);
        (new ContractGenerationService($templates))->render($prepared, $snapshot, $this->directory.'/generated.docx');
    }

    public function invalidSnapshots(): array
    {
        return [
            ['civil_status', 'Widowed'], ['lessee_full_name', null],
            ['lessee_full_name', "Hidden\x00text"], ['lessee_full_name', '${unresolved}'],
            ['residential_address', str_repeat('a', 1001)],
        ];
    }

    private function snapshot(array $placeholders): array
    {
        $snapshot = array_fill_keys($placeholders, 'Synthetic value');
        return array_merge($snapshot, [
            'civil_status' => 'Single', 'gender' => 'Female',
            'lessee_full_name' => 'SYNTHETIC & <LESSEE>',
            'contract_control_no' => 'TEST-2026-000001',
            'government_id_type' => 'Test ID', 'government_id_number' => 'TEST-ID-001',
            'helper_1' => 'Synthetic Helper One', 'helper_2' => 'Synthetic Helper Two',
            'lease_period' => 'January 1, 2027 to June 30, 2027',
        ]);
    }
}
