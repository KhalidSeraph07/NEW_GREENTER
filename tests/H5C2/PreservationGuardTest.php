<?php
namespace Tests\H5C2;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
final class PreservationGuardTest extends TestCase
{
    public function test_preservation_failure_returns_nonzero_instead_of_only_rendering_an_error(): void
    {
        $p=new Process([PHP_BINARY,dirname(__DIR__).'/h5c2-preservation.php','after'],dirname(__DIR__,2),['APP_ENV'=>'testing','DB_DATABASE'=>'emisor_h2_test','CACHE_STORE'=>'array']);
        $p->run();$this->assertSame(1,$p->getExitCode());$this->assertStringContainsString('Preservacion solo Compose ficticio.',$p->getErrorOutput());
    }
}
