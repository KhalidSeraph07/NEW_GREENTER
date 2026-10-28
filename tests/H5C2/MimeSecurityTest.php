<?php
namespace Tests\H5C2;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Header\ParameterizedHeader;
final class MimeSecurityTest extends TestCase
{
    public function test_patched_dependency_rejects_header_parameter_name_injection(): void
    {
        $this->expectException(\Symfony\Component\Mime\Exception\RfcComplianceException::class);
        $this->expectExceptionMessage('contains non-token characters');
        $h=new ParameterizedHeader('Content-Disposition','attachment',["name\r\nX-C2-Injection"=>'fictitious']);$h->toString();
    }
    public function test_valid_fictitious_filename_remains_supported(): void
    {
        $h=new ParameterizedHeader('Content-Disposition','attachment',['filename'=>'ficticio.xml']);$this->assertStringContainsString('filename=ficticio.xml',$h->toString());
    }
}
