<?php

namespace Tests\Feature\Cbt;

use App\Services\Cbt\DocxQuestionParser;
use Tests\TestCase;
use ZipArchive;

class BeCbt13DocxImportSecurityTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_string($path) && file_exists($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    private function createDocxWithDocumentXml(string $documentXml): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'cbt_sec_').'.docx';
        $this->tempFiles[] = $tmpPath;

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $tmpPath;
    }

    public function test_p0_01_xxe_doctype_and_external_entity_is_rejected(): void
    {
        $secretFile = tempnam(sys_get_temp_dir(), 'xxe_secret_');
        $this->tempFiles[] = $secretFile;
        file_put_contents($secretFile, 'RAHASIA_SERVER_SISTLABUM_12345');

        $maliciousXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file://{$secretFile}">
]>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>1. &xxe;</w:t></w:r></w:p>
    <w:p><w:r><w:t>A. Pilihan A</w:t></w:r></w:p>
    <w:p><w:r><w:t>B. Pilihan B</w:t></w:r></w:p>
    <w:p><w:r><w:t>KUNCI: A</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $docxPath = $this->createDocxWithDocumentXml($maliciousXml);
        $parser = new DocxQuestionParser;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DOCTYPE/ENTITY/XInclude');

        $parser->parse($docxPath);
    }

    public function test_p0_01_xinclude_payload_is_rejected(): void
    {
        $secretFile = tempnam(sys_get_temp_dir(), 'xinc_secret_');
        $this->tempFiles[] = $secretFile;
        file_put_contents($secretFile, 'RAHASIA_XINCLUDE_98765');

        $maliciousXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:xi="http://www.w3.org/2001/XInclude">
  <w:body>
    <w:p><w:r><w:t>1. <xi:include href="file://{$secretFile}" parse="text"/></w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $docxPath = $this->createDocxWithDocumentXml($maliciousXml);
        $parser = new DocxQuestionParser;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DOCTYPE/ENTITY/XInclude');

        $parser->parse($docxPath);
    }

    public function test_p0_01_oversized_document_xml_zip_bomb_is_rejected(): void
    {
        $hugeXml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'
            .str_repeat('A', 5_500_000)
            .'</w:t></w:r></w:p></w:body></w:document>';

        $docxPath = $this->createDocxWithDocumentXml($hugeXml);
        $parser = new DocxQuestionParser;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('5 MB');

        $parser->parse($docxPath);
    }

    public function test_p0_01_valid_normal_docx_still_parses_cleanly(): void
    {
        $validXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>1. Berapakah hasil dari 2 + 3?</w:t></w:r></w:p>
    <w:p><w:r><w:t>A. 4</w:t></w:r></w:p>
    <w:p><w:r><w:t>B. 5</w:t></w:r></w:p>
    <w:p><w:r><w:t>KUNCI: B</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $docxPath = $this->createDocxWithDocumentXml($validXml);
        $parser = new DocxQuestionParser;
        $result = $parser->parse($docxPath);

        $this->assertEmpty($result['errors']);
        $this->assertCount(1, $result['questions']);
        $this->assertStringContainsString('2 + 3', $result['questions'][0]['body']['content']);
    }
}
