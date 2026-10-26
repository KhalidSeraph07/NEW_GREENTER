<?php

declare(strict_types=1);

namespace App\Boletas;

use App\Invoices\InvoiceInput;
use DateTimeImmutable;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;
use Greenter\Xml\Builder\InvoiceBuilder;
use Greenter\Xml\Builder\SummaryBuilder;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use Greenter\XMLSecLibs\XMLSecurityKey;
use Greenter\XMLSecLibs\XMLSecurityDSig;
use RuntimeException;

final class FictitiousXml
{
    public function boleta(array $s): array
    {
        $this->guard();
        if ($s['issuer_ruc'] !== '00000000000' || $s['series'] !== 'B000' || $s['document_type'] !== '03') {
            throw new RuntimeException('Solo identidad H4 ficticia.');
        }
        $decimal = fn ($key) => (float) InvoiceInput::decimal($s[$key]);
        $detail = (new SaleDetail())->setCodProducto('H4-TEST')->setUnidad('NIU')->setDescripcion($s['description'])
            ->setCantidad((float) InvoiceInput::decimal($s['quantity_milli'], 3))->setMtoValorUnitario($decimal('unit_price_cents'))
            ->setMtoValorVenta($decimal('subtotal_cents'))->setMtoBaseIgv($decimal('subtotal_cents'))
            ->setPorcentajeIgv(18)->setTipAfeIgv('10')->setIgv($decimal('tax_cents'))->setTotalImpuestos($decimal('tax_cents'))
            ->setMtoPrecioUnitario((float) InvoiceInput::decimal(intdiv($s['unit_price_cents'] * 118 + 50, 100)));
        $invoice = (new Invoice())->setUblVersion('2.1')->setTipoOperacion('0101')->setTipoDoc('03')
            ->setSerie('B000')->setCorrelativo((string) $s['number'])->setFechaEmision(new DateTimeImmutable($s['emission_date'].'T12:00:00-05:00'))
            ->setTipoMoneda('PEN')->setCompany($this->company())
            ->setClient((new Client())->setTipoDoc('1')->setNumDoc('00000000')->setRznSocial('CONSUMIDOR FICTICIO H4'))
            ->setMtoOperGravadas($decimal('subtotal_cents'))->setValorVenta($decimal('subtotal_cents'))
            ->setMtoIGV($decimal('tax_cents'))->setTotalImpuestos($decimal('tax_cents'))
            ->setSubTotal($decimal('total_cents'))->setMtoImpVenta($decimal('total_cents'))->setDetails([$detail])
            ->setLegends([(new Legend())->setCode('1000')->setValue('PRUEBA H4 SIN VALIDEZ FISCAL')]);
        return $this->sign((new InvoiceBuilder())->build($invoice));
    }

    public function summary(array $s): array
    {
        $this->guard();
        $details = [];
        foreach ($s['members'] as $member) {
            $details[] = (new SummaryDetail())->setTipoDoc('03')->setSerieNro('B000-'.$member['number'])
                ->setClienteTipo('1')->setClienteNro('00000000')->setEstado('1')
                ->setTotal((float) InvoiceInput::decimal($member['total_cents']))
                ->setMtoOperGravadas((float) InvoiceInput::decimal($member['subtotal_cents']))
                ->setMtoIGV((float) InvoiceInput::decimal($member['tax_cents']))->setPorcentajeIgv(18);
        }
        $summary = (new Summary())->setCompany($this->company())->setMoneda('PEN')->setCorrelativo((string) $s['number'])
            ->setFecGeneracion(new DateTimeImmutable($s['reference_date'].'T12:00:00-05:00'))
            ->setFecResumen(new DateTimeImmutable($s['issue_date'].'T12:00:00-05:00'))->setDetails($details);
        if ($summary->getName() !== $s['name']) { throw new RuntimeException('Identidad RC inconsistente.'); }
        return $this->sign((new SummaryBuilder())->build($summary));
    }

    private function company(): Company
    {
        return (new Company())->setRuc('00000000000')->setRazonSocial('EMISOR FICTICIO H4')
            ->setAddress((new Address())->setUbigueo('000000')->setDepartamento('FICTICIO')->setProvincia('FICTICIO')
                ->setDistrito('FICTICIO')->setDireccion('DIRECCION FICTICIA')->setCodigoPais('PE'));
    }

    private function guard(): void
    {
        if (config('h4.mode') !== 'fictitious') { throw new RuntimeException('H4 solo ficticio.'); }
    }

    private function sign(string $unsigned): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'H4 TEST ONLY NO FISCAL VALIDITY'], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        if (!$cert || !openssl_pkey_export($key, $private) || !openssl_x509_export($cert, $public)) {
            throw new RuntimeException('Certificado ficticio no generado.');
        }
        $signer = new SignedXml(); $signer->setCertificate($private.$public);
        $signer->setSignatureAlgorithm(XMLSecurityKey::RSA_SHA256); $signer->setDigestAlgorithm(XMLSecurityDSig::SHA256);
        $signed = $signer->signXml($unsigned);
        unset($private, $key, $signer);
        if (!(new SignedXml())->verifyXml($signed)) { throw new RuntimeException('Firma H4 invalida.'); }
        return ['unsigned' => $unsigned, 'xml' => $signed, 'certificate' => $public];
    }
}
