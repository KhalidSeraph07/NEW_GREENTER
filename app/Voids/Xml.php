<?php
declare(strict_types=1);
namespace App\Voids;
use App\Invoices\InvoiceInput;
use DateTimeImmutable;
use Greenter\Model\Company\Company;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use Greenter\XMLSecLibs\XMLSecurityDSig;
use Greenter\XMLSecLibs\XMLSecurityKey;
use RuntimeException;
final class Xml
{
    public function render(array $s): array
    {
        if(config('h5c1.mode')!=='fictitious' || $s['mode']!=='fictitious'){throw new RuntimeException('Solo baja H5-C1 ficticia.');}
        $company=(new Company())->setRuc('00000000000')->setRazonSocial('EMISOR FICTICIO H5-C1'); $details=[];
        if($s['route']==='RA'){
            foreach($s['members'] as $m){
                if(!in_array([$m['document_type'],$m['series']],[['01','F000'],['07','FC00'],['08','FD00']],true)){throw new RuntimeException('Miembro RA fuera de alcance.');}
                $details[]=(new \Greenter\Model\Voided\VoidedDetail())->setTipoDoc($m['document_type'])->setSerie($m['series'])
                    ->setCorrelativo((string)$m['number'])->setDesMotivoBaja($s['reason']);
            }
            $doc=(new \Greenter\Model\Voided\Voided())->setCompany($company)->setCorrelativo((string)$s['number'])
                ->setFecGeneracion(new DateTimeImmutable($s['reference_date'].'T12:00:00-05:00'))
                ->setFecComunicacion(new DateTimeImmutable($s['issue_date'].'T12:00:00-05:00'))->setDetails($details);
            $unsigned=(new \Greenter\Xml\Builder\VoidedBuilder())->build($doc);
        }elseif($s['route']==='RC' && $s['operation']==='3'){
            foreach($s['members'] as $m){
                if(!in_array([$m['document_type'],$m['series']],[['03','B000'],['07','BC00'],['08','BD00']],true)){throw new RuntimeException('Miembro RC/3 fuera de alcance.');}
                $detail=(new \Greenter\Model\Summary\SummaryDetail())->setTipoDoc($m['document_type'])->setSerieNro($m['series'].'-'.$m['number'])
                    ->setClienteTipo('1')->setClienteNro('00000000')->setEstado('3')
                    ->setTotal((float)InvoiceInput::decimal($m['total_cents']))->setMtoOperGravadas((float)InvoiceInput::decimal($m['subtotal_cents']))
                    ->setMtoIGV((float)InvoiceInput::decimal($m['tax_cents']))->setPorcentajeIgv(18);
                if($m['original']!==null){$detail->setDocReferencia((new \Greenter\Model\Sale\Document())->setTipoDoc('03')->setNroDoc($m['original']['number']));}
                $details[]=$detail;
            }
            $doc=(new \Greenter\Model\Summary\Summary())->setCompany($company)->setMoneda('PEN')->setCorrelativo((string)$s['number'])
                ->setFecGeneracion(new DateTimeImmutable($s['reference_date'].'T12:00:00-05:00'))
                ->setFecResumen(new DateTimeImmutable($s['issue_date'].'T12:00:00-05:00'))->setDetails($details);
            $unsigned=(new \Greenter\Xml\Builder\SummaryBuilder())->build($doc);
        }else{throw new RuntimeException('Ruta de baja no autorizada.');}
        if($doc->getName()!==$s['name']){throw new RuntimeException('Identidad de baja inconsistente.');}
        return $this->sign($unsigned);
    }
    private function sign(string $unsigned): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'H5-C1 TEST ONLY NO FISCAL VALIDITY'], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        if (!$cert || !openssl_pkey_export($key, $private) || !openssl_x509_export($cert, $public)) {
            throw new RuntimeException('Certificado de prueba H5-C1 no generado.');
        }
        $signer = new SignedXml(); $signer->setCertificate($private.$public);
        $signer->setSignatureAlgorithm(XMLSecurityKey::RSA_SHA256); $signer->setDigestAlgorithm(XMLSecurityDSig::SHA256);
        $signed = $signer->signXml($unsigned); unset($private, $key, $signer);
        if (!(new SignedXml())->verifyXml($signed)) { throw new RuntimeException('Firma local H5-C1 invalida.'); }
        return ['unsigned' => $unsigned, 'xml' => $signed, 'certificate' => $public];
    }
}
